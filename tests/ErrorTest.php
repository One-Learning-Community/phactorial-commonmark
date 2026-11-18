<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class ErrorTest extends TestCase
{
    /** @param array<string, mixed> $math */
    private function convert(string $markdown, array $math = []): string
    {
        $environment = new Environment(['math' => $math]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    public function test_unknown_command_shows_source(): void
    {
        $html = $this->convert('$\nosuchcommand{x}$');

        self::assertStringContainsString('<code class="math math-error">$\nosuchcommand{x}$</code>', $html);
        self::assertStringNotContainsString('<svg', $html);
    }

    public function test_render_mode_draws_anyway(): void
    {
        $html = $this->convert('$\nosuchcommand{x}$', ['on_error' => 'render']);

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('math-error', $html);
    }

    public function test_a_formula_the_reader_only_approximated_still_draws(): void
    {
        $html = $this->convert('$\color{red}{x}$');

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('math-error', $html);
    }

    public function test_valid_formula_is_not_an_error(): void
    {
        $html = $this->convert('$x^2$');

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('math-error', $html);
    }

    public function test_backtick_form_keeps_its_delimiters(): void
    {
        self::assertStringContainsString('<code class="math math-error">$`\nosuchcommand`$</code>', $this->convert('$`\nosuchcommand`$'));
    }

    public function test_double_dollar_inline_keeps_its_delimiters(): void
    {
        self::assertStringContainsString('<code class="math math-error">$$\nosuchcommand$$</code>', $this->convert('a $$\nosuchcommand$$ b'));
    }

    public function test_display_block_error_keeps_block_delimiters(): void
    {
        $html = $this->convert("$$\n\\nosuchcommand\n$$");

        self::assertStringContainsString('<div class="math math-display math-error"><code>$$', $html);
        self::assertStringContainsString("\\nosuchcommand\n$$</code></div>", $html);
        self::assertStringNotContainsString('<p>', $html);
    }

    public function test_fence_error_uses_double_dollar_delimiters(): void
    {
        $html = $this->convert("```math\n\\nosuchcommand\n```");

        self::assertStringContainsString('<div class="math math-display math-error"><code>$$\nosuchcommand$$</code></div>', $html);
    }

    public function test_source_is_escaped(): void
    {
        $html = $this->convert('$\nosuchcommand{<b>&}$');

        self::assertStringContainsString('{&lt;b&gt;&amp;}', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function test_error_in_mathml_output_still_shows_source(): void
    {
        self::assertStringContainsString('math-error', $this->convert('$\nosuchcommand$', ['output' => 'mathml']));
    }

    public function test_other_formulas_in_the_paragraph_still_render(): void
    {
        $html = $this->convert('$x$ and $\nosuchcommand$');

        self::assertSame(1, substr_count($html, '<svg'));
        self::assertSame(1, substr_count($html, 'math-error'));
    }
}
