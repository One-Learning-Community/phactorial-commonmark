<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\Config\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class ConfigTest extends TestCase
{
    /** @param array<string, mixed> $math */
    private function convert(array $math, string $markdown = 'a $x$'): string
    {
        $environment = new Environment(['math' => $math]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    private static function svgWidth(string $html): float
    {
        if (preg_match('/<svg[^>]* width="([\d.]+)"/', $html, $m) !== 1) {
            self::fail($html);
        }

        return (float) $m[1];
    }

    public function test_size_scales_svg(): void
    {
        $small = self::svgWidth($this->convert(['size' => 16]));
        $large = self::svgWidth($this->convert(['size' => 32]));

        self::assertEqualsWithDelta(2.0, $large / $small, 0.05);
    }

    public function test_invalid_output_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->convert(['output' => 'png']);
    }

    public function test_zero_size_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->convert(['size' => 0]);
    }

    public function test_invalid_on_error_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->convert(['on_error' => 'explode']);
    }

    public function test_on_error_accepts_both_values(): void
    {
        self::assertStringContainsString('<svg', $this->convert(['on_error' => 'source']));
        self::assertStringContainsString('<svg', $this->convert(['on_error' => 'render']));
    }
}
