<?php

namespace Ernestdefoe\Festoon\Style;

use Ernestdefoe\Festoon\Config;
use Ernestdefoe\Festoon\Look\Look;
use Flarum\Foundation\Event\ClearingCache;
use Flarum\Settings\Event\Reset;
use Flarum\Settings\Event\Saved;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\Filesystem as Files;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes every look's decoration stylesheet, and the fireworks script, to
 * assets/ — when Festoon is enabled (see PublishOnEnable), when the cache is
 * cleared and when its settings are saved or reset, the same moments core
 * rebuilds its own assets. A page request only links to what is there.
 *
 * Each name carries a hash of everything that shapes the file, including the
 * installed version of Festoon, so a changed setting or an update is a new
 * file and no browser keeps a stale one. Files from before are removed, and
 * the list of what is published is kept in the cache so a page never asks
 * the disk (a remote one is an HTTP call).
 */
class Publisher
{
    public const DIR = 'extensions/ernestdefoe-festoon';

    protected const PUBLISHED = 'ernestdefoe-festoon.published';

    protected Filesystem $assets;

    public function __construct(
        Factory $filesystems,
        protected SettingsRepositoryInterface $settings,
        protected Files $files,
        protected Cache $cache,
        protected LoggerInterface $log
    ) {
        $this->assets = $filesystems->disk('flarum-assets');
    }

    public function subscribe(Dispatcher $events): void
    {
        // A cleared cache also rewrites files of the same name, in case they were changed by hand.
        $events->listen(ClearingCache::class, fn () => $this->publishAll(true));
        $events->listen(Saved::class, function (Saved $event) {
            if (preg_grep('/^ernestdefoe-festoon\./', array_keys($event->settings))) {
                $this->publishAll();
            }
        });
        $events->listen(Reset::class, function (Reset $event) {
            if ($event->extensionId === 'ernestdefoe-festoon') {
                $this->publishAll();
            }
        });
    }

    /** @param array<string, bool> $parts */
    public function stylesheet(Look $look, array $parts): string
    {
        $hash = substr(md5(json_encode([$look->toArray(), $parts, Config::version()]) ?: ''), 0, 10);

        return self::DIR."/$look->id-$hash.css";
    }

    public function fireworks(): string
    {
        return self::DIR.'/fireworks-'.substr(md5(Config::version()), 0, 10).'.js';
    }

    /** Whether a file is published, from the cached list; the disk only if that is gone. */
    public function has(string $path): bool
    {
        $published = $this->cache->get(self::PUBLISHED);

        return is_array($published) ? in_array($path, $published, true) : $this->assets->exists($path);
    }

    /** The built fireworks script, or '' (logged) when the build is missing. */
    public function fireworksSource(): string
    {
        $source = __DIR__.'/../../js/dist/fireworks.js';
        if ($this->files->exists($source)) {
            return $this->files->get($source);
        }
        $this->log->error("[ernestdefoe/festoon] Cannot read $source; fireworks will not play until it is rebuilt.");

        return '';
    }

    public function url(string $path): string
    {
        // Flarum's assets disk is a Cloud disk, which knows its public URL.
        return $this->assets instanceof Cloud ? $this->assets->url($path) : '/assets/'.$path;
    }

    /**
     * Every look, with the forum's current settings, read fresh: the Config
     * singleton may hold the values from before a save in this request.
     * A file whose name is already there has the same contents, so it is only
     * written again when $force is set. Nothing here may stop core's cache
     * clear or a settings save: a failure is logged, and pages carry their
     * decorations inline until the next publish.
     */
    public function publishAll(bool $force = false): void
    {
        try {
            $config = new Config($this->settings);
            $parts = $config->parts();
            $files = [];

            foreach ($config->looks() as $look) {
                $files[$this->stylesheet($look, $parts)] = fn () => Decorations::css($look, $parts);
            }

            if ($script = $this->fireworksSource()) {
                $files[$this->fireworks()] = fn () => $script;
            }

            foreach ($files as $path => $contents) {
                if ($force || ! $this->assets->exists($path)) {
                    $this->assets->put($path, $contents());
                }
            }

            foreach (array_diff($this->assets->files(self::DIR), array_keys($files)) as $old) {
                $this->assets->delete($old);
            }

            $this->cache->forever(self::PUBLISHED, array_keys($files));
        } catch (Throwable $e) {
            $this->cache->forget(self::PUBLISHED);
            $this->log->error('[ernestdefoe/festoon] Publishing the decoration files failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }
}
