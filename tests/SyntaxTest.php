<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\MarkdownParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;
use Phactorial\CommonMark\Node\MathBlock;

final class SyntaxTest extends TestCase
{
    private function convert(string $markdown, string $output = 'mathml'): string
    {
        $environment = new Environment(['math' => ['output' => $output]]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    private static function occurrences(string $html, string $needle): int
    {
        return substr_count($html, $needle);
    }

    /** @return iterable<string, array{string, array<string, int>}> */
    public static function counts(): iterable
    {
        yield 'inline' => ['area $\pi r^2$ here', ['<span class="math math-inline">' => 1, '<span class="math math-display">' => 0, '<div class="math' => 0, '<math' => 1]];
        yield 'backtick inline' => ['Euler $`e^{i\pi}+1=0`$', ['<span class="math math-inline">' => 1, '<math' => 1]];
        yield 'double dollar in text is inline' => ['a $$\sum k$$ b', ['<span class="math math-inline">' => 1, '<span class="math math-display">' => 0, '<div' => 0, '<p>' => 1, '<math' => 1]];
        yield 'display span in a display paragraph' => ["\$\$a\$\$ and \$\$b\$\$", ['<span class="math math-display">' => 2, '<div' => 0, '<p>' => 1, '<math' => 2]];
        yield 'display block' => ["$$\nx\n$$", ['<div class="math math-display">' => 1, '<p>' => 0, '<math' => 1]];
        yield 'one-line display block' => ['$$x = 1$$', ['<div class="math math-display">' => 1, '<p>' => 0, '<math' => 1]];
        yield 'math fence' => ["```math\nx^2\n```", ['<div class="math math-display">' => 1, '<pre>' => 0, '<math' => 1]];
        yield 'other fence untouched' => ["```php\n\$a\$\n```", ['<pre><code class="language-php">$a$' => 1, '<math' => 0]];
        yield 'prices' => ['costs $5 and $10', ['<math' => 0, 'costs $5 and $10' => 1]];
        yield 'escaped dollar' => ['a lone \$ sign', ['<math' => 0, 'a lone $ sign' => 1]];
        yield 'code span' => ['`$x$`', ['<code>$x$</code>' => 1, '<math' => 0]];
        yield 'spaced' => ['$ spaced $', ['<math' => 0, '$ spaced $' => 1]];
        yield 'escaped dollar then code' => ['$10. A lone \$ and `$x$`', ['<math' => 0, '<code>$x$</code>' => 1]];
        yield 'blockquote' => ["> \$\$\n> x\n> \$\$", ['<blockquote>' => 1, '<div class="math math-display">' => 1, '<math' => 1, '<p>' => 0]];
        yield 'list item' => ["- item\n\n  \$\$\n  x\n  \$\$", ['<li>' => 1, '<div class="math math-display">' => 1, '<math' => 1]];
        yield 'multibyte' => ['é $x$ ü', ['<p>é <span class="math math-inline">' => 1, '</span> ü</p>' => 1, '<math' => 1]];
        yield 'strong and link' => ['**bold $v$** and [link $x^2$](https://example.com)', ['<strong>bold <span class="math math-inline">' => 1, '<a href="https://example.com">link $x^2$</a>' => 1, '<math' => 1]];
    }

    /** @param array<string, int> $expected */
    #[DataProvider('counts')]
    public function test_syntax(string $markdown, array $expected): void
    {
        $html = $this->convert($markdown);
        foreach ($expected as $needle => $count) {
            self::assertSame($count, self::occurrences($html, $needle), "$needle in: $html");
        }
    }

    public function test_crlf_matches_lf(): void
    {
        $lf = $this->convert("$$\nx\n$$\n");
        $crlf = $this->convert("$$\r\nx\r\n$$\r\n");

        self::assertSame(1, self::occurrences($crlf, '<div class="math math-display">'));
        self::assertSame($lf, $crlf);
    }

    public function test_crlf_inline_and_multiline_matches_lf(): void
    {
        $lf = $this->convert("a \$x\$ b\n\n\$\$\na\n+\nb\n\$\$\n\nend \$y\$");
        $crlf = $this->convert("a \$x\$ b\r\n\r\n\$\$\r\na\r\n+\r\nb\r\n\$\$\r\n\r\nend \$y\$");

        self::assertSame($lf, $crlf);
    }

    public function test_multibyte_inside_math_keeps_cursor_aligned(): void
    {
        $html = $this->convert('é $\text{é}$ ü $y$ ö');

        self::assertSame(2, self::occurrences($html, '<span class="math math-inline">'));
        self::assertStringContainsString('<p>é ', $html);
        self::assertStringContainsString('</span> ü ', $html);
        self::assertStringContainsString('</span> ö</p>', $html);
    }

    public function test_math_block_fields_are_read_only(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());
        $document = (new MarkdownParser($environment))->parse("$$\nx\n$$");

        $block = null;
        foreach ($document->iterator() as $node) {
            if ($node instanceof MathBlock) {
                $block = $node;
            }
        }
        self::assertInstanceOf(MathBlock::class, $block);
        self::assertSame('x', $block->tex);
        self::assertStringStartsWith('$$', $block->source);

        try {
            $property = 'tex';
            $block->$property = 'y'; // @phpstan-ignore assign.propertyProtectedSet
            self::fail('Assigning tex must throw.');
        } catch (\Error) {
            self::assertSame('x', $block->tex);
        }
    }

    public function test_svg_output_is_the_default(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());
        $html = (new MarkdownConverter($environment))->convert('a $x$')->getContent();

        self::assertStringContainsString('<svg', $html);
        self::assertStringContainsString('role="img"', $html);
        self::assertStringNotContainsString('<math', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function unclosedOpeners(): iterable
    {
        yield 'backtick dollar' => ['$`a '];
        yield 'single dollar' => ['$a '];
        yield 'double dollar' => ['$$a '];
    }

    #[DataProvider('unclosedOpeners')]
    public function test_unclosed_backtick_dollar_openers_parse_in_linear_time(string $opener): void
    {
        $count = $opener === '$`a ' ? 40000 : 20000;
        $markdown = str_repeat($opener, $count);

        $limit = ini_set('memory_limit', '512M');
        try {
            $start = microtime(true);
            $this->convert($markdown);
            $elapsed = microtime(true) - $start;
        } finally {
            gc_collect_cycles();
            // Memory the parse still holds can sit above the old limit; keeping the raised one is harmless.
            @ini_set('memory_limit', (string) $limit);
        }

        self::assertLessThan(2.5, $elapsed, "$count unclosed '$opener' openers took {$elapsed}s");
    }

    /** @return iterable<string, array{string}> */
    public static function lateCloser(): iterable
    {
        yield 'inline double, closer on a later line' => [str_repeat('$$a ', 20000) . "\n\$\$."];
        yield 'inline double, closers followed by letters' => [str_repeat('$$a ', 20000) . '$$b'];
        yield 'display double, escaped last closer' => ['$$' . str_repeat('a $$b ', 20000) . '\\$$'];
        yield 'single dollar and letters' => [str_repeat('$a$b ', 20000)];
        yield 'double dollar, code span in every candidate' => ['x ' . str_repeat('$$a `b` ', 16000) . '$$ '];
        yield 'single dollar, code span in every candidate' => [str_repeat('$a `b` c$ ', 50000)];
        yield 'display, code span in every candidate' => ['$$' . str_repeat(' `a` $$', 50000)];
        yield 'code spans inside openers' => [str_repeat('$a `b` ', 20000)];
    }

    #[DataProvider('lateCloser')]
    public function test_openers_with_a_distant_or_unusable_closer_parse_in_linear_time(string $markdown): void
    {
        // League builds a node per code span, which needs more than the default 128M on the larger inputs.
        $limit = ini_set('memory_limit', '512M');
        try {
            $start = microtime(true);
            $this->convert($markdown);
            $elapsed = microtime(true) - $start;
        } finally {
            gc_collect_cycles();
            // Memory the parse still holds can sit above the old limit; keeping the raised one is harmless.
            @ini_set('memory_limit', (string) $limit);
        }

        self::assertLessThan(2.5, $elapsed, "took {$elapsed}s");
    }

    public function test_image_alt_text_keeps_the_formula(): void
    {
        $html = $this->convert('![a $x$](u.png)');

        self::assertStringContainsString('alt="a $x$"', $html);
        self::assertSame(0, self::occurrences($html, '<math'));
    }

    public function test_backtick_dollar_closer_on_a_later_line_of_the_paragraph_matches(): void
    {
        $html = $this->convert("a \$`x\ny`\$ b");

        self::assertSame(1, self::occurrences($html, '<span class="math math-inline">'), $html);
        self::assertSame(1, self::occurrences($html, '<math'), $html);
    }
}
