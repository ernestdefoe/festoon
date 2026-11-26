<?php

namespace Ernestdefoe\Festoon\Content;

use Ernestdefoe\Festoon\Config;
use Ernestdefoe\Festoon\Look\Look;
use Ernestdefoe\Festoon\Look\Schedule;
use Ernestdefoe\Festoon\Style\Decorations;
use Ernestdefoe\Festoon\Style\Palette;
use Ernestdefoe\Festoon\Style\Publisher;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Puts today's look on the page, decided here on the server so the first paint
 * is already right — no flash of the forum's normal colours while a script
 * catches up.
 *
 * - The colours go inline in <head>, after the forum stylesheet, where core
 *   intends inline overrides to win. They are a few hundred bytes.
 * - The decorations are a stylesheet file per look, written to assets/ by
 *   Style\Publisher and cached by the browser like the forum's own CSS. If it
 *   is not there yet (an update before the cache was cleared), the same rules
 *   go inline, so the page is right either way and a request never writes.
 * - Falling snow, leaves and the rest are a few dozen empty spans before
 *   </body>, moved by CSS. Only fireworks need a script, and it is added
 *   only on a day that has them.
 */
class InjectFestoon
{
    public const PREVIEW = 'festoon';

    public function __construct(protected Config $config, protected Schedule $schedule, protected Publisher $publisher, protected TranslatorInterface $translator)
    {
    }

    public function __invoke(Document $document, Request $request): void
    {
        if (! $this->config->enabled()) {
            return;
        }

        $actor = RequestUtil::getActor($request);
        $preview = $this->preview($actor, $request);
        $look = $preview ?? $this->schedule->active();

        if ($look === null) {
            return;
        }

        $parts = $this->config->parts();

        if ($parts['colors']) {
            $document->head[] = '<style id="festoon-colors">'.(new Palette($look->get('primary'), $look->get('secondary')))->css().'</style>';
        }

        $off = ! $actor->isGuest() && $actor->getPreference('festoonDecorations') === false;

        if ($off) {
            $document->extraAttributes['class'][] = 'Festoon-off';
        } else {
            $this->decorate($document, $look, $parts);
        }

        // The occasion's name, for the preview notice and for the tip on the
        // piece beside the forum's name. Only the script puts it on the page,
        // so the HTML crawlers read carries none of it.
        $tip = ! $off && $parts['header'] && $look->hasHeaderPiece();

        if ($preview !== null || $tip || $look->get('effect') === 'fireworks') {
            $document->payload['festoon'] = array_filter([
                'look' => $look->id,
                'preview' => $preview !== null,
                'name' => $this->name($look),
                'tip' => $tip,
                'sky' => $look->get('effect') === 'fireworks' && $parts['effects'] ? $look->sky() : null,
            ]);
        }
    }

    /** A look an admin asked to see today with ?festoon=id. */
    protected function preview(User $actor, Request $request): ?Look
    {
        $asked = $request->getQueryParams()[self::PREVIEW] ?? null;

        return is_string($asked) && $actor->isAdmin() ? $this->config->look($asked) : null;
    }

    /** @param array<string, bool> $parts */
    protected function decorate(Document $document, Look $look, array $parts): void
    {
        $path = $this->publisher->stylesheet($look, $parts);
        $document->head[] = $this->publisher->has($path)
            ? '<link rel="stylesheet" href="'.e($this->publisher->url($path)).'">'
            // Rules only: escaped emoji and URL-encoded pictures, never a "<".
            : '<style id="festoon-decorations">'.Decorations::css($look, $parts).'</style>';

        if (! $parts['effects']) {
            return;
        }

        $document->foot[] = self::falling($look->get('effect'));

        if ($look->get('effect') === 'fireworks') {
            $script = $this->publisher->fireworks();
            $document->foot[] = $this->publisher->has($script)
                ? '<script defer src="'.e($this->publisher->url($script)).'"></script>'
                // Until the next publish, inline: only on a fireworks day, and only then.
                : '<script>'.str_replace('</', '<\\/', $this->publisher->fireworksSource()).'</script>';
        }
    }

    protected function name(Look $look): string
    {
        return $look->group() === 'custom' ? $look->name() : $this->translator->trans('ernestdefoe-festoon.lib.looks.'.$look->id);
    }

    /** The falling layer: empty spans, each with its own lane, speed and delay. */
    public static function falling(string $effect): string
    {
        $colors = [
            'leaves' => ['#c2551b', '#d98a1e', '#9e3d12'],
            'petals' => ['#f4a6c0', '#f7c6d6', '#e987a8'],
            'hearts' => ['#c2185b', '#f06292', '#f48fb1'],
            'confetti' => ['#c62828', '#c9a227', '#e8c547'],
        ];

        if (! in_array($effect, ['snow', 'leaves', 'petals', 'hearts', 'embers', 'confetti'], true)) {
            return '';
        }

        $count = $effect === 'snow' ? 28 : 16;
        $html = '<div class="Festoon-fall Festoon-fall--'.$effect.'" aria-hidden="true">';

        for ($i = 0; $i < $count; $i++) {
            // Spread by a fixed stride, not randomness: the same page renders
            // the same bytes, and lanes never bunch up.
            $style = sprintf(
                '--x:%d%%;--t:%ds;--d:-%.1fs;--dx:%dpx;--r:%ddeg;--s:%dpx',
                ($i * 37 + 11) % 100,
                ($effect === 'snow' ? 12 : 13) + $i % 7,
                fmod($i * 1.7, 18),
                ($i % 5 - 2) * ($effect === 'leaves' ? 40 : 16),
                in_array($effect, ['leaves', 'confetti', 'petals'], true) ? 180 + $i * 40 : 0,
                4 + $i % 4
            );
            if (isset($colors[$effect])) {
                $style .= ';--c:'.$colors[$effect][$i % 3];
            }
            $html .= '<i style="'.$style.'"></i>';
        }

        return $html.'</div>';
    }
}
