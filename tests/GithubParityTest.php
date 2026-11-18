<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;
use Phactorial\CommonMark\Tests\Support\SegmentRenderer;

/**
 * Compares the formulas this extension finds with the ones GitHub's renderer marks. The expected HTML in
 * tests/fixtures/github was captured by bin/capture-github.php.
 */
final class GithubParityTest extends TestCase
{
    /** Cases the extension cannot match, with the reason. */
    private const array DIVERGENCES = [
        'double-dollar-run' => 'GitHub reads $$a$$b$$ as one formula, text b, and drops the trailing $$',
    ];

    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        /** @var array<string, string> $cases */
        $cases = require __DIR__ . '/fixtures/github/cases.php';
        foreach (array_keys($cases) as $name) {
            if (! isset(self::DIVERGENCES[$name])) {
                yield $name => [$name];
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function divergences(): iterable
    {
        foreach (self::DIVERGENCES as $name => $_) {
            yield $name => [$name];
        }
    }

    #[DataProvider('cases')]
    public function test_math_segments_match_github(string $name): void
    {
        $this->assertMatchesGithub($name);
    }

    #[Group('known-divergence')]
    #[DataProvider('divergences')]
    public function test_known_divergence_matches_github(string $name): void
    {
        $this->assertMatchesGithub($name);
    }

    private function assertMatchesGithub(string $name): void
    {
        /** @var array<string, string> $cases */
        $cases = require __DIR__ . '/fixtures/github/cases.php';
        $github = (string) file_get_contents(__DIR__ . "/fixtures/github/$name.html");
        $ours = $this->convert($cases[$name]);

        self::assertSame(self::githubSegments($github), self::ourSegments($ours), "formulas differ for $name\nGitHub: $github\nOurs: $ours");
        self::assertSame(self::githubText($github), self::ourText($ours), "text differs for $name\nGitHub: $github\nOurs: $ours");
    }

    private function convert(string $markdown): string
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new MathExtension());
        $renderer = new SegmentRenderer();
        $environment->addRenderer(MathInline::class, $renderer, 100);
        $environment->addRenderer(MathBlock::class, $renderer, 100);
        $environment->addRenderer(FencedCode::class, $renderer, 100);

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }

    /** @return list<array{string, string}> */
    private static function githubSegments(string $html): array
    {
        preg_match_all('#<math-renderer class="js-(inline|display)-math"[^>]*>(.*?)</math-renderer>#s', $html, $m, PREG_SET_ORDER);

        return array_map(static function (array $match): array {
            $source = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
            $double = strlen($source) >= 4 && str_starts_with($source, '$$') && str_ends_with($source, '$$');
            $tex = substr($source, $double ? 2 : 1, $double ? -2 : -1);

            return [$match[1], self::collapse($tex)];
        }, $m);
    }

    /** @return list<array{string, string}> */
    private static function ourSegments(string $html): array
    {
        preg_match_all('#<span data-kind="(inline|display)" data-tex="([^"]*)"></span>#', $html, $m, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], self::collapse(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5))], $m);
    }

    private static function githubText(string $html): string
    {
        return self::text((string) preg_replace('#<math-renderer .*?</math-renderer>#s', "\u{1}", $html));
    }

    private static function ourText(string $html): string
    {
        return self::text((string) preg_replace('#<span data-kind=[^>]*></span>#', "\u{1}", $html));
    }

    private static function text(string $html): string
    {
        // Block tags separate words; inline tags (GitHub's syntax highlighting spans, <code>, <a>) do not.
        $html = (string) preg_replace('#</?(?:p|br|td|th|tr|li|ul|ol|div|pre|table|thead|tbody|blockquote|markdown-accessiblity-table)\b[^>]*>#', ' ', $html);

        return self::collapse(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
