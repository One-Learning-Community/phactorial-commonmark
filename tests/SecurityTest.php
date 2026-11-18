<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class SecurityTest extends TestCase
{
    /** @param array<string, mixed> $math */
    private function convert(string $markdown, array $math = []): string
    {
        $environment = new Environment(['math' => $math, 'html_input' => 'escape', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function modes(): iterable
    {
        yield 'svg' => [[]];
        yield 'mathml' => [['output' => 'mathml']];
        yield 'render on error' => [['on_error' => 'render']];
    }

    /** @param array<string, mixed> $math */
    #[\PHPUnit\Framework\Attributes\DataProvider('modes')]
    public function test_script_in_text_is_never_emitted(array $math): void
    {
        $html = $this->convert('$\text{<script>alert(1)</script>}$', $math);

        self::assertStringNotContainsString('<script', $html);
    }

    public function test_script_in_an_unknown_command_is_escaped_in_the_source_fallback(): void
    {
        $html = $this->convert('$\nosuchcommand{<script>alert(1)</script>}$');

        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_html_around_math_is_escaped(): void
    {
        $html = $this->convert('<b>$x$</b> <img src="$x$">');

        self::assertStringContainsString('&lt;b&gt;', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<svg', $html);
    }

    /** @param array<string, mixed> $math */
    #[\PHPUnit\Framework\Attributes\DataProvider('modes')]
    public function test_javascript_href_never_reaches_the_output(array $math): void
    {
        // The reader has no \href, so the formula is a dropped unknown command; either way no URL is emitted.
        foreach (['$\href{javascript:alert(1)}{x}$', "$$\n\\href{javascript:alert(1)}{x}\n$$"] as $markdown) {
            self::assertStringNotContainsString('<a ', $this->convert($markdown, $math));
            self::assertStringNotContainsString('href=', $this->convert($markdown, $math));
        }
    }

    public function test_javascript_href_source_is_shown_only_as_escaped_text(): void
    {
        $html = $this->convert('$\href{javascript:alert(1)}{x}$');

        self::assertStringContainsString('math-error', $html);
        self::assertStringNotContainsString('<a', $html);
    }

    public function test_a_ten_thousand_character_formula_finishes_quickly(): void
    {
        $started = microtime(true);
        $html = $this->convert('$' . str_repeat('x+', 4999) . 'x$');

        self::assertLessThan(5.0, microtime(true) - $started);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('math-error', $html);
    }

    public function test_a_thousand_formulas_finish_quickly(): void
    {
        $markdown = implode("\n\n", array_map(static fn (int $i): string => "Formula \$x_{$i}^2 + y\$ and \$\\nosuchcommand\$.", range(1, 1000)));
        $started = microtime(true);
        $html = $this->convert($markdown);
        $elapsed = microtime(true) - $started;

        self::assertLessThan(5.0, $elapsed);
        self::assertSame(1000, substr_count($html, 'math-error'));
    }
}
