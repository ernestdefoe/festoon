<?php

namespace Ernestdefoe\Festoon\Tests\unit;

use Ernestdefoe\Festoon\Config;
use Ernestdefoe\Festoon\Style\Decorations;
use PHPUnit\Framework\TestCase;

class MotionTest extends TestCase
{
    private const SVG = '<svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="currentColor"/></svg>';

    /** @param array<string, mixed> $look */
    private function css(array $look, array $parts = []): string
    {
        $config = new Config(new ArraySettings([
            Config::CUSTOM => json_encode([['id' => 'x', 'name' => 'Day', 'svg' => self::SVG, 'emoji' => '🎲'] + $look]),
        ]));

        return Decorations::css($config->look('custom-x'), $parts + $config->parts());
    }

    public function test_your_own_picture_moves_by_transform_and_opacity_only(): void
    {
        foreach (['float', 'sway', 'spin', 'pulse', 'twinkle'] as $motion) {
            $css = $this->css(['ornament' => 'svg', 'corner' => 'emoji', 'motion' => $motion]);

            $this->assertMatchesRegularExpression("/@keyframes festoon-$motion\\{([^{}]*\\{[^{}]*\\})+\\}/", $css, $motion);
            preg_match("/@keyframes festoon-$motion\\{((?:[^{}]*\\{[^{}]*\\})+)\\}/", $css, $m);
            preg_match_all('/([a-z-]+):/', preg_replace('/^[^{]*\{|\}[^{]*\{/', '', $m[1]), $props);
            $this->assertSame([], array_diff($props[1], ['transform', 'opacity']), $motion);

            $this->assertStringContainsString('.Header-title::after,', $css);
            $this->assertStringContainsString('.WelcomeHero::after{animation-delay:-', $css);
            // Members who ask for less motion get it still.
            $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion:reduce\)\{.*\.Header-title::after[^{]*\{animation:none\}/', $css, $motion);
        }
    }

    public function test_speed_changes_the_duration(): void
    {
        $this->assertStringContainsString('animation:festoon-sway 4.2s ', $this->css(['ornament' => 'svg', 'motion' => 'sway', 'speed' => 'slow']));
        $this->assertStringContainsString('animation:festoon-sway 2.4s ', $this->css(['ornament' => 'svg', 'motion' => 'sway']));
        $this->assertStringContainsString('animation:festoon-sway 1.44s ', $this->css(['ornament' => 'svg', 'motion' => 'sway', 'speed' => 'fast']));
    }

    public function test_nothing_moves_unless_asked_for_on_your_own_picture(): void
    {
        $this->assertDoesNotMatchRegularExpression('/animation:festoon-(?!fall)/', $this->css(['ornament' => 'svg']));
        $this->assertStringNotContainsString('@keyframes festoon-float', $this->css(['ornament' => 'svg', 'motion' => 'float'], ['effects' => false]));
        // Built-in pieces keep their own drawing; the preset is only for yours.
        $this->assertStringNotContainsString('@keyframes festoon-float', $this->css(['ornament' => 'sparkles', 'corner' => 'stars', 'motion' => 'float']));
        // Never the divider between posts: its line would move with it.
        $this->assertStringNotContainsString('PostStream-item:has(+.PostStream-item)::after{animation', $this->css(['corner' => 'svg', 'motion' => 'spin']));
        // Values outside the list are dropped.
        $this->assertDoesNotMatchRegularExpression('/animation:festoon-(?!fall)/', $this->css(['ornament' => 'svg', 'motion' => 'x}body{color:red']));
        $this->assertStringContainsString('animation:festoon-pulse 1.6s ', $this->css(['ornament' => 'svg', 'motion' => 'pulse', 'speed' => 'x']));
    }

    public function test_the_piece_beside_the_name_keeps_its_place_in_the_row(): void
    {
        // Layout properties never change while it moves, so the logo is never pushed.
        $css = $this->css(['ornament' => 'svg', 'motion' => 'float', 'size' => 40]);
        $this->assertStringContainsString('width:40px;height:40px', $css);
        $this->assertDoesNotMatchRegularExpression('/@keyframes[^}]*(width|height|margin|top|left)\s*:/', $css);
    }
}
