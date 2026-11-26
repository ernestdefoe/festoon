<?php

/*
 * Festoon for Flarum 2 — a holiday theme that changes with the time of year.
 * Free and MIT licensed.
 *
 * Today's look is chosen on the server, in the forum's timezone, so the page
 * arrives already dressed. Colours are a small inline stylesheet; decorations
 * are a stylesheet file per look, published ahead of time; the forum bundle is under 1 KB
 * gzipped, and the fireworks script is added only on days that have them.
 */

use Ernestdefoe\Festoon\Content\AdminPayload;
use Ernestdefoe\Festoon\Content\InjectFestoon;
use Ernestdefoe\Festoon\Provider;
use Ernestdefoe\Festoon\PublishOnEnable;
use Ernestdefoe\Festoon\Style\Publisher;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->content(InjectFestoon::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less')
        ->content(AdminPayload::class),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default('ernestdefoe-festoon.enabled', true),

    // A member's own switch for the decorations. Colours stay, so the forum
    // still matches the season; the garlands, hats and falling things go.
    (new Extend\User())
        ->registerPreference('festoonDecorations', fn ($value) => (bool) $value, true),

    (new Extend\ServiceProvider())
        ->register(Provider::class),

    // Every look's stylesheet is written when Festoon is enabled, the cache is
    // cleared or its settings are saved or reset, so a page request never writes.
    (new Extend\Event())
        ->subscribe(Publisher::class),

    new PublishOnEnable(),
];
