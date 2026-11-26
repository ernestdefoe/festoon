<?php

namespace Ernestdefoe\Festoon\Style;

use Ernestdefoe\Festoon\Look\Look;

/**
 * The decoration stylesheet for one look.
 *
 * It is written once to a file under assets/ and served by the web server
 * with the forum's other assets, so a member downloads it once per look, not
 * with every page. Every rule hangs off html:not(.Festoon-off), which is how a
 * member's "turn off decorations" switch takes effect the moment it is
 * clicked, before the next page load leaves the file out altogether.
 *
 * Decorations are pseudo-elements on Flarum's own elements, so no component
 * is replaced and nothing else's markup changes.
 */
final class Decorations
{
    private const ON = 'html:not(.Festoon-off) ';

    private string $css = '';

    /** Still versions of moving pieces, for members who ask for less motion. */
    private string $still = '';

    /** @var list<string> The administrator's own pictures that are drawn, for the animation. */
    private array $own = [];

    private ?string $emoji;

    private ?string $svg;

    private function __construct(private readonly Look $look)
    {
        $this->emoji = self::content($look->get('emoji'));
        $this->svg = in_array('svg', [$look->get('ornament'), $look->get('corner')], true) ? Svg::uri($look->get('svg'), $look->get('primary')) : null;
    }

    /** @param array<string, bool> $parts */
    public static function css(Look $look, array $parts): string
    {
        $d = new self($look);

        if ($parts['header'] ?? true) {
            $d->garland();
            $d->headerPiece();
        }
        if ($parts['ornaments'] ?? true) {
            $d->corners();
        }
        if ($parts['trims'] ?? true) {
            $d->trims();
        }
        if ($parts['hats'] ?? true) {
            $d->hats();
        }
        if ($parts['effects'] ?? true) {
            $d->effects();
        }

        return $d->still === '' ? $d->css : $d->css.'@media (prefers-reduced-motion:reduce){'.$d->still.'}';
    }

    /**
     * The image itself goes in one rule per selector list, so a drawing used
     * in three places is in the file once; a still twin only when it moves.
     * $under is an extra image layer drawn beneath the drawing.
     */
    private function bg(string $selector, string $body, int $w, int $h, string $rest, string $under = ''): void
    {
        $this->css .= "$selector{background:url(\"".Art::uri($body, $w, $h, true)."\") $rest".($under ? ",$under" : '').'}';
        if (Art::moves($body)) {
            $this->still .= "$selector{background-image:url(\"".Art::uri($body, $w, $h, false).'")'.($under ? ','.strtok($under, ' ') : '').'}';
        }
    }

    private function garland(): void
    {
        $on = self::ON;

        if ($garland = Art::garland($this->look->get('garland'))) {
            // Desktop: hung from the header's bottom edge. On phones the
            // header is the slide-out drawer, so it hangs from the top bar.
            $this->css .= $on.'.App-header::after,'.$on.'.App-navigation::after{content:"";position:absolute;left:0;right:0;top:100%;height:'.Art::GARLAND_HEIGHT.'px;pointer-events:none;z-index:2}'
                .'@media (max-width:767.98px){'.$on.'.App-header::after{display:none}}'
                .'@media (min-width:768px){'.$on.'.App-navigation::after{display:none}}'
                // ESPN CFB Ticker sits 36px tall right under the top bar;
                // hang the garland below it rather than across the scores.
                .$on.'.has-cfb-ticker-top .App-header::after,'.$on.'.has-cfb-ticker-top .App-navigation::after{top:calc(100% + 36px)}';
            $this->bg($on.'.App-header::after,'.$on.'.App-navigation::after', $garland, 960, Art::GARLAND_HEIGHT, '0 0/960px '.Art::GARLAND_HEIGHT.'px repeat-x');
        }
    }

    /** The piece beside the forum's name, and the tip button laid over it. */
    private function headerPiece(): void
    {
        $on = self::ON;
        // Core draws an image logo as a block, so a plain inline ornament
        // would wrap under it. A flex row keeps it beside the logo or the
        // name, centred like the title already is in the phone drawer.
        $row = $on.'.Header-title{display:flex;align-items:center}'
            .'@media (max-width:767.98px){'.$on.'.Header-title{justify-content:center}}';

        $kind = $this->look->get('ornament');
        $size = $this->look->size();
        $piece = $on.'.Header-title::after{content:"";flex:none;margin-inline-start:6px;pointer-events:none;';
        $width = 0;

        if ($kind === 'emoji') {
            if ($this->emoji) {
                $width = (int) round($size * 1.1);
                $this->own[] = $on.'.Header-title::after';
                $this->css .= $row.$on.'.Header-title::after{content:'.$this->emoji.';flex:none;margin-inline-start:6px;font-size:'.round($size * .72).'px;line-height:1;pointer-events:none}';
            }
        } elseif ($kind === 'svg') {
            if ($this->svg) {
                $width = $size;
                $this->own[] = $on.'.Header-title::after';
                $this->css .= $row.$piece."width:{$size}px;height:{$size}px;background:url(\"$this->svg\") center/contain no-repeat}";
            }
        } elseif ($ornament = Art::ornament($kind)) {
            $width = 48;
            $this->css .= $row.$piece.'width:48px;height:36px}';
            $this->bg($on.'.Header-title::after', $ornament, 48, 36, 'center/contain no-repeat');
        }

        if ($width) {
            // The occasion's name on hover, or on a tap on a phone: the
            // forum script lays an empty button over the piece.
            // It follows the piece in the title's flex row and its negative
            // margin lays it over the piece, wherever the row puts it.
            $this->css .= $on.'.Header-title::after{order:1}'
                .$on.'.Festoon-tip{order:2;position:relative;flex:none;align-self:stretch;width:'.($width + 6).'px;margin-inline-start:-'.($width + 6).'px;padding:0;border:0;background:none;cursor:help}'
                .$on.'.Festoon-tip:is(:hover,:focus-visible,:focus)::after{content:attr(aria-label);position:absolute;top:calc(100% + 4px);left:50%;transform:translateX(-50%);z-index:5;width:max-content;max-width:min(260px,80vw);padding:4px 8px;border-radius:4px;background:var(--text-color);color:var(--body-bg);font-size:12px;font-weight:600;line-height:1.4;text-align:start;white-space:normal;pointer-events:none}'
                .$on.'.Festoon-tip:focus-visible{outline:2px solid var(--primary-color);outline-offset:2px;border-radius:4px}'
                .'.Festoon-off .Festoon-tip{display:none}';
        }
    }

    /** The ornament on the welcome banner, widgets, the opening post and between posts. */
    private function corners(): void
    {
        $on = self::ON;
        $kind = $this->look->get('corner');
        $corner = match ($kind) {
            'emoji' => $this->emoji,
            'svg' => $this->svg,
            default => Art::corner($kind),
        };

        if (! $corner) {
            return;
        }

        // The welcome banner, in both bottom corners, the right one mirrored.
        $this->css .= $on.'.WelcomeHero{position:relative}'
            .$on.'.WelcomeHero::before,'.$on.'.WelcomeHero::after{content:"";position:absolute;bottom:10px;width:64px;height:64px;pointer-events:none}'
            .$on.'.WelcomeHero::before{left:16px}'.$on.'.WelcomeHero::after{right:16px;transform:scaleX(-1)}'
            .'@media (max-width:767.98px){'.$on.'.WelcomeHero::before,'.$on.'.WelcomeHero::after{display:none}}';

        // Widgets — Bespoke's, and every widget FoF Widgets places — and the
        // opening post get the same piece, tucked into the top-right corner.
        $widget = $on.'.Bespoke-widget::after,'.$on.'.FofWidgets-WidgetSection>*::after,'.$on.'.PostStream-item[data-number="1"] .CommentPost::after';
        $this->css .= $on.'.FofWidgets-WidgetSection>*,'.$on.'.PostStream-item[data-number="1"] .CommentPost{position:relative}'
            .$widget.'{content:"";position:absolute;top:-10px;inset-inline-end:-6px;width:34px;height:34px;pointer-events:none;z-index:1}';

        // A small one in the middle of each divider between posts.
        $divider = $on.'.PostStream-item:has(+.PostStream-item)::after';
        $this->css .= $divider.'{height:18px;margin-top:-9px;margin-bottom:-9px;background-color:transparent;position:relative;z-index:1}';

        if ($kind === 'emoji') {
            // Not the divider, here or for a picture: its line is the same box and would move too.
            array_push($this->own, $on.'.WelcomeHero::before', $on.'.WelcomeHero::after', ...explode(',', $widget));
            // Text, not a drawing: centred in the same boxes, never mirrored,
            // and the divider's line stops either side of it.
            $this->css .= $on.'.WelcomeHero::before,'.$on.'.WelcomeHero::after{content:'.$corner.';font-size:40px;line-height:64px;text-align:center}'
                .$on.'.WelcomeHero::after{transform:none}'
                .$widget.'{content:'.$corner.';font-size:22px;line-height:34px;text-align:center}'
                .$divider.'{content:'.$corner.';font-size:13px;line-height:18px;text-align:center;background:linear-gradient(90deg,var(--control-bg) calc(50% - 16px),transparent 0 calc(50% + 16px),var(--control-bg) 0) 0 50%/100% 1px no-repeat}';
        } elseif ($kind === 'svg') {
            array_push($this->own, $on.'.WelcomeHero::before', $on.'.WelcomeHero::after', ...explode(',', $widget));
            $this->css .= $on.'.WelcomeHero::before,'.$on.'.WelcomeHero::after,'.$widget.'{background:url("'.$corner.'") center/contain no-repeat}'
                .$on.'.WelcomeHero::after{transform:none}'
                .$divider.'{background:url("'.$corner.'") center/18px 18px no-repeat,linear-gradient(90deg,var(--control-bg) calc(50% - 14px),transparent 0 calc(50% + 14px),var(--control-bg) 0) 0 50%/100% 1px no-repeat}';
        } else {
            $this->bg($on.'.WelcomeHero::before,'.$on.'.WelcomeHero::after,'.$widget, $corner, 34, 34, 'center/contain no-repeat');
            // The divider keeps core's line, drawn under the ornament.
            $this->bg($divider, $corner, 34, 34, 'center/18px 18px no-repeat', 'linear-gradient(var(--control-bg),var(--control-bg)) 0 50%/100% 1px no-repeat');
        }
    }

    /** A strip of snow or stripes along the top of the main buttons and widgets. */
    private function trims(): void
    {
        $on = self::ON;
        $trim = $this->look->trim();
        $targets = [$on.'.IndexPage-newDiscussion', $on.'.DiscussionPage-nav .SplitDropdown-button.Button--primary', $on.'.Bespoke-widget', $on.'.FofWidgets-WidgetSection>*'];
        $before = implode(',', array_map(fn ($t) => "$t::before", $targets));

        if ($trim === 'snow') {
            $this->css .= implode(',', $targets).'{position:relative;overflow:visible}'
                .$before.'{content:"";position:absolute;left:2px;right:2px;top:-6px;height:12px;pointer-events:none;z-index:1;background:url("'.Art::uri(Art::snow(), 28, 12, false).'") 0 0/28px 12px repeat-x}';
        } elseif (is_array($trim)) {
            $stops = [];
            foreach ($trim as $i => $c) {
                $stops[] = $c.' '.($i * 8).'px '.(($i + 1) * 8).'px';
            }
            $this->css .= implode(',', $targets).'{position:relative;overflow:visible}'
                .$before.'{content:"";position:absolute;left:3px;right:3px;top:0;height:3px;pointer-events:none;z-index:1;border-radius:2px;background:repeating-linear-gradient(90deg,'.implode(',', $stops).')}';
        }
    }

    private function hats(): void
    {
        $on = self::ON;

        if ($hat = Art::hat($this->look->get('hat'))) {
            // On the big avatar beside each post (desktop; .Post-side holds it at
            // its top left) and on the small one in the post header (phones).
            $sel = $on.'.CommentPost .Post-side::before,'.$on.'.CommentPost .Post-header .PostUser-name a::before';
            $this->css .= $on.'.CommentPost .Post-side,'.$on.'.CommentPost .Post-header .PostUser-name a{position:relative}'
                .$sel.'{content:"";position:absolute;pointer-events:none;z-index:1}'
                .$on.'.CommentPost .Post-side::before{left:2px;top:-21px;width:60px;height:36px}'
                .$on.'.CommentPost .Post-header .PostUser-name a::before{left:1px;top:-17px;width:30px;height:18px}'
                .'@media (max-width:767.98px){'.$on.'.CommentPost .Post-side::before{display:none}}'
                .'@media (min-width:768px){'.$on.'.CommentPost .Post-header .PostUser-name a::before{display:none}}';
            $this->bg($sel, $hat, 60, 36, 'center/contain no-repeat');
        }
    }

    /** Fireworks' night sky, the falling layer, and your own pictures moving. */
    private function effects(): void
    {
        $on = self::ON;

        if ($this->look->get('effect') === 'fireworks') {
            // A night sky on the welcome banner for the show to play on.
            $this->css .= $on.'.WelcomeHero{background:linear-gradient(#050a1f,#0e1a3d 70%,#1a2550);--hero-color:#c9d3ea;color:#c9d3ea}'
                .$on.'.WelcomeHero .Hero-title,'.$on.'.WelcomeHero h1{color:#fff}'
                .$on.'.WelcomeHero .container{position:relative;z-index:1}'
                .'@media (min-width:768px){'.$on.'.WelcomeHero .container{padding-top:64px;padding-bottom:56px}}'
                .'.Festoon-sky{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}';
        }

        $this->css .= self::fallingCss();

        if ($this->own && $this->look->get('motion') !== 'none') {
            $this->css .= self::motionCss($this->look->get('motion'), $this->look->get('speed'), $this->own);
            $this->still .= implode(',', $this->own).'{animation:none}';
        }

        $this->still .= '.Festoon-fall{display:none}';
    }

    /**
     * The administrator's emoji as a CSS string, every code point escaped, so
     * no character of it can close the string or the rule. Null when empty.
     */
    public static function content(string $text): ?string
    {
        $cps = $text === '' ? [] : array_map('mb_ord', mb_str_split($text));

        return $cps ? '"'.implode('', array_map(fn ($cp) => '\\'.dechex($cp).' ', $cps)).'"' : null;
    }

    /**
     * The administrator's own emoji or picture, moving. Transform and opacity
     * only, so nothing is laid out again: the piece beside the forum's name
     * keeps its place in the row and never pushes the logo.
     *
     * @param list<string> $selectors
     */
    private static function motionCss(string $motion, string $speed, array $selectors): string
    {
        // [seconds at normal speed, keyframes, timing]
        [$seconds, $frames, $timing] = match ($motion) {
            'float' => [3.0, 'from{transform:translateY(4%)}to{transform:translateY(-8%)}', 'ease-in-out infinite alternate'],
            'sway' => [2.4, 'from{transform:rotate(-8deg)}to{transform:rotate(8deg)}', 'ease-in-out infinite alternate'],
            'spin' => [10.0, 'to{transform:rotate(360deg)}', 'linear infinite'],
            'pulse' => [1.6, 'to{transform:scale(1.12)}', 'ease-in-out infinite alternate'],
            default => [1.8, 'to{opacity:.35;transform:scale(.92)}', 'ease-in-out infinite alternate'],
        };
        $seconds *= ['slow' => 1.75, 'fast' => .6][$speed] ?? 1;
        $s = fn (float $x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.').'s';

        return "@keyframes festoon-$motion{{$frames}}"
            .implode(',', $selectors)."{animation:festoon-$motion ".$s($seconds)." $timing".($motion === 'sway' ? ';transform-origin:50% 100%' : '').'}'
            // The banner's two corners out of step, so they don't move as one.
            .(in_array(self::ON.'.WelcomeHero::after', $selectors, true) ? self::ON.'.WelcomeHero::after{animation-delay:-'.$s($seconds / 2).'}' : '');
    }

    /**
     * Falling things: a few dozen fixed spans, moved by transform only. They
     * sit just under the header (1000), so the header and its menus, the
     * composer, dropdowns, modals and alerts all draw over them.
     */
    private static function fallingCss(): string
    {
        return self::ON.'.Festoon-fall{position:fixed;inset:0;pointer-events:none;z-index:999;overflow:hidden}'
            .'.Festoon-off .Festoon-fall{display:none}'
            .'.Festoon-fall i{position:absolute;top:-16px;left:var(--x);animation:festoon-fall var(--t) linear var(--d) infinite;opacity:.75}'
            .'@keyframes festoon-fall{to{transform:translate3d(var(--dx),calc(100vh + 32px),0) rotate(var(--r))}}'
            .'.Festoon-fall--snow i{width:var(--s);height:var(--s);border-radius:50%;background:#a9c3de}'
            .'.Festoon-fall--embers i{width:4px;height:4px;border-radius:50%;background:#f28c28;box-shadow:0 0 4px #ffb347}'
            .'.Festoon-fall--leaves i{width:9px;height:14px;border-radius:0 80% 0 80%;background:var(--c)}'
            .'.Festoon-fall--petals i{width:10px;height:6px;border-radius:50%;background:var(--c)}'
            .'.Festoon-fall--confetti i{width:8px;height:3px;background:var(--c)}'
            .'.Festoon-fall--hearts i{width:10px;height:10px;background:var(--c);clip-path:path("M5 9C-1 5 0 0 3 0c1 0 2 1 2 2c0-1 1-2 2-2c3 0 4 5-2 9z")}';
    }
}
