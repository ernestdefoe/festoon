<?php

namespace Ernestdefoe\Festoon\Tests\integration\api;

use Ernestdefoe\Festoon\Style\Publisher;
use Ernestdefoe\Festoon\PublishOnEnable;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Event\ClearingCache;
use Flarum\Settings\Event\Reset;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory;

class FestoonTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-festoon');
        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'quiet', 'email' => 'quiet@machine.local', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1, 'joined_at' => '2026-01-01 00:00:00', 'preferences' => json_encode(['festoonDecorations' => false])],
            ],
        ]);
    }

    private function page(?int $as = null, string $path = '/'): string
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        $request = $this->request('GET', (string) parse_url($path, PHP_URL_PATH), $as ? ['authenticatedAs' => $as] : []);
        $response = $this->send($request->withQueryParams($query));
        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    private function published(): array
    {
        return $this->app()->getContainer()->make(Factory::class)->disk('flarum-assets')->files(Publisher::DIR);
    }

    /** Seasons cover the whole year, so some look is always up. */
    public function test_a_guest_gets_todays_decorations_inline_until_published_then_as_a_file(): void
    {
        $this->app()->getContainer()->make(Factory::class)->disk('flarum-assets')->deleteDirectory(Publisher::DIR);
        $this->app()->getContainer()->make(Repository::class)->flush();

        $html = $this->page();
        $this->assertStringContainsString('<style id="festoon-colors">', $html);
        $this->assertStringContainsString('<style id="festoon-decorations">', $html);
        $this->assertSame([], $this->published(), 'a page request never writes');

        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new ClearingCache());

        $html = $this->page();
        $this->assertMatchesRegularExpression('#assets/extensions/ernestdefoe-festoon/[a-z0-9-]+\.css#', $html);
        $this->assertStringNotContainsString('festoon-decorations', $html);
        $this->assertNotEmpty(preg_grep('/fireworks-[0-9a-f]{10}\.js$/', $this->published()));
    }

    public function test_saving_its_settings_publishes_new_files_and_removes_the_old(): void
    {
        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new ClearingCache());
        $before = $this->published();

        $response = $this->send($this->request('POST', '/api/settings', [
            'authenticatedAs' => 1,
            'json' => ['ernestdefoe-festoon.parts' => json_encode(['hats' => false])],
        ]));
        $this->assertSame(204, $response->getStatusCode());

        $after = $this->published();
        $this->assertCount(count($before), $after);
        $this->assertNotEmpty(array_diff($before, $after), 'old stylesheets removed');
        $this->assertNotEmpty(preg_grep('#/christmas-[0-9a-f]{10}\.css$#', $after));
    }

    public function test_enabling_publishes_and_another_extensions_reset_does_not(): void
    {
        $container = $this->app()->getContainer();
        $disk = $container->make(Factory::class)->disk('flarum-assets');
        $disk->deleteDirectory(Publisher::DIR);
        $container->make(Repository::class)->flush();

        $container->make(Dispatcher::class)->dispatch(new Reset(User::find(1), 'flarum-tags', ['x']));
        $this->assertSame([], $this->published());

        (new PublishOnEnable())->onEnable($container, $container->make(ExtensionManager::class)->getExtension('ernestdefoe-festoon'));
        $this->assertNotEmpty(preg_grep('#/christmas-[0-9a-f]{10}\.css$#', $this->published()));
        $this->assertStringNotContainsString('festoon-decorations', $this->page());
    }

    /**
     * A deploy that rebuilds core's assets without clearing the cache (no
     * event Festoon hears) leaves new file names unpublished. Pages must stay
     * whole: the decorations inline, and on a fireworks day the script too.
     */
    public function test_an_unpublished_change_is_served_inline_without_writing(): void
    {
        $container = $this->app()->getContainer();
        $container->make(Dispatcher::class)->dispatch(new ClearingCache());
        $before = $this->published();

        // Changes the publisher never heard about, like a new version's names.
        $container->make(SettingsRepositoryInterface::class)->set('ernestdefoe-festoon.parts', json_encode(['hats' => false]));
        $disk = $container->make(Factory::class)->disk('flarum-assets');
        $disk->delete(preg_grep('/fireworks-/', $before));
        $container->make(Repository::class)->flush();
        $before = $this->published();

        $html = $this->page(1, '/?festoon=newyear');
        $this->assertStringContainsString('<style id="festoon-decorations">', $html);
        $this->assertStringContainsString('FestoonFireworks', $html);
        $this->assertDoesNotMatchRegularExpression('#<script>[^<]*</(?!script)#', $html);
        $this->assertSame($before, $this->published(), 'a page request never writes');
    }

    public function test_preview_is_for_admins_only(): void
    {
        $this->assertStringNotContainsString('--primary-color:#b71c1c', $this->page(2, '/?festoon=christmas'));
        $this->assertStringNotContainsString('--primary-color:#b71c1c', $this->page(null, '/?festoon=christmas'));
        $this->assertStringContainsString('--primary-color:#b71c1c', $this->page(1, '/?festoon=christmas'));
    }

    public function test_a_member_who_turned_decorations_off_keeps_the_colors_only(): void
    {
        $html = $this->page(3);

        $this->assertStringContainsString('Festoon-off', $html);
        $this->assertStringContainsString('<style id="festoon-colors">', $html);
        $this->assertStringNotContainsString('ernestdefoe-festoon/', $html);
        $this->assertStringNotContainsString('Festoon-fall', $html);
    }

    public function test_switched_off_forum_wide_adds_nothing(): void
    {
        $this->setting('ernestdefoe-festoon.enabled', '0');

        $html = $this->page();
        $this->assertStringNotContainsString('festoon-colors', $html);
        $this->assertStringNotContainsString('ernestdefoe-festoon/', $html);
    }

    /**
     * Choosing and drawing today's look must not cost a single query.
     *
     * Core does some writes on a timer (last seen, token activity), so one
     * request can carry a few more queries than the next for reasons that
     * have nothing to do with Festoon. Each state is measured three times and
     * the lowest count compared.
     */
    public function test_no_queries_of_its_own(): void
    {
        $count = function (string $enabled, int $as) {
            $this->setting('ernestdefoe-festoon.enabled', $enabled);
            $this->page($as); // warm the decoration file and caches
            $db = $this->database();
            $counts = [];

            for ($i = 0; $i < 3; $i++) {
                $db->flushQueryLog();
                $db->enableQueryLog();
                $this->page($as);
                $counts[] = count($db->getQueryLog());
                $db->disableQueryLog();
            }

            return min($counts);
        };

        foreach ([1, 2] as $as) {
            $this->assertSame($count('0', $as), $count('1', $as), "user $as");
        }
    }

    public function test_a_wrong_preview_name_is_ignored(): void
    {
        $this->assertStringContainsString('<style id="festoon-colors">', $this->page(1, '/?festoon=../../etc/passwd'));
    }
}
