<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class GfmTest extends TestCase
{
    private function convert(string $markdown): string
    {
        $environment = new Environment(['math' => ['output' => 'mathml']]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    public function test_math_in_a_table_cell(): void
    {
        $html = $this->convert("| a | b |\n|---|---|\n| \$x^2\$ | text |\n");

        self::assertSame(1, preg_match('#<td>\s*<span class="math math-inline"><math#', $html), $html);
        self::assertSame(1, substr_count($html, '<math'));
    }

    public function test_math_in_strikethrough(): void
    {
        $html = $this->convert('~~struck $x$~~');

        self::assertSame(1, preg_match('#<del>struck <span class="math math-inline"><math#', $html), $html);
    }

    public function test_dollars_inside_an_autolink_stay_in_the_link(): void
    {
        $html = $this->convert('https://example.com/$a$');

        self::assertSame(0, substr_count($html, '<math'), $html);
        self::assertStringContainsString('<a href="https://example.com/$a$">https://example.com/$a$</a>', $html);
    }

    public function test_display_block_and_task_list_coexist(): void
    {
        $html = $this->convert("- [x] done\n\n\$\$\nx\n\$\$\n");

        self::assertSame(1, substr_count($html, '<div class="math math-display">'));
        self::assertStringContainsString('type="checkbox"', $html);
    }
}
