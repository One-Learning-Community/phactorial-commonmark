<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

/** The opt-in \(…\) and \[…\] syntax. */
final class LatexDelimiterTest extends TestCase
{
    /** @param array<string, mixed> $math */
    private function convert(string $markdown, array $math = ['latex_delimiters' => true]): string
    {
        $environment = new Environment(['math' => ['output' => 'mathml'] + $math]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    public function test_off_by_default(): void
    {
        self::assertSame("<p>a (x) b</p>\n", $this->convert('a \(x\) b', []));
        self::assertSame("<p>[x]</p>\n", $this->convert('\[x\]', []));
    }

    /** @return iterable<string, array{string, array<string, int>}> */
    public static function counts(): iterable
    {
        yield 'inline' => ['a \(x^2\) b', ['<span class="math math-inline">' => 1, '<math' => 1]];
        yield 'display in a sentence' => ['a \[x\] b', ['<span class="math math-display">' => 1, '<div' => 0, '<math' => 1]];
        yield 'display block' => ["\\[\nx = 1\n\\]", ['<div class="math math-display">' => 1, '<p>' => 0, '<math' => 1]];
        yield 'display block in a list item' => ["- item\n\n  \\[\n  x\n  \\]", ['<li>' => 1, '<div class="math math-display">' => 1, '<math' => 1]];
        yield 'display block in a blockquote' => ["> \\[\n> x\n> \\]", ['<blockquote>' => 1, '<div class="math math-display">' => 1, '<math' => 1]];
        yield 'inline does not cross a line' => ["\\(a\nb\\)", ['<math' => 0]];
        yield 'escaped closer is not a closer' => ['\[a\\\\]', ['<math' => 0, '[a\]' => 1]];
        yield 'blank is not math' => ['\( \)', ['<math' => 0]];
        yield 'code span inside ends it' => ['\(a `b` c\)', ['<math' => 0, '<code>b</code>' => 1]];
        yield 'code span holds it' => ['`\(x\)`', ['<code>\(x\)</code>' => 1, '<math' => 0]];
        yield 'dollars still work' => ['$a$ and \(b\)', ['<math' => 2]];
        yield 'escaped backslash before the opener' => ['\\\\(x\)', ['<math' => 0]];
    }

    /** @param array<string, int> $expected */
    #[DataProvider('counts')]
    public function test_syntax(string $markdown, array $expected): void
    {
        $html = $this->convert($markdown);
        foreach ($expected as $needle => $count) {
            self::assertSame($count, substr_count($html, $needle), "$needle in: $html");
        }
    }

    public function test_unclosed_falls_back_to_escapes(): void
    {
        self::assertSame("<p>[x and (y</p>\n", $this->convert('\[x and \(y'));
    }

    public function test_link_text_reads_as_it_would_without_the_option(): void
    {
        self::assertStringContainsString('<a href="u">see (x)</a>', $this->convert('[see \(x\)](u)'));
        self::assertStringContainsString('<a href="#fn1">[1]</a>', $this->convert('[\[1\]](#fn1)'));
        self::assertStringContainsString('<a href="u">(a \ b)</a>', $this->convert('[\(a \\\\ b\)](u)'));
    }

    public function test_image_alt_reads_as_it_would_without_the_option(): void
    {
        self::assertStringContainsString('alt="alt (x)"', $this->convert('![alt \(x\)](i.png)'));
    }

    public function test_an_unreadable_formula_shows_its_source(): void
    {
        $html = $this->convert('a \(\nosuchcommand{x}\) b', ['latex_delimiters' => true, 'on_error' => 'source']);

        self::assertStringContainsString('<code class="math math-error">\(\nosuchcommand{x}\)</code>', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function hostile(): iterable
    {
        yield 'unclosed inline openers' => [str_repeat('\(a ', 20000)];
        yield 'unclosed display openers' => [str_repeat('\[a ', 20000)];
        yield 'inline openers, closer past a newline' => [str_repeat("\\(a\n", 20000) . '\)'];
        yield 'code span in every candidate' => [str_repeat('\(a `b` ', 20000) . '\)'];
        yield 'only escaped closers' => [str_repeat('\[a\\\\] ', 20000)];
    }

    #[DataProvider('hostile')]
    public function test_hostile_paragraphs_parse_in_linear_time(string $markdown): void
    {
        $limit = ini_set('memory_limit', '512M');
        try {
            $start = microtime(true);
            $this->convert($markdown);
            $elapsed = microtime(true) - $start;
        } finally {
            gc_collect_cycles();
            @ini_set('memory_limit', (string) $limit);
        }

        self::assertLessThan(2.5, $elapsed, "took {$elapsed}s");
    }
}
