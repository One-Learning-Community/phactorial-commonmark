<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use Phactorial\CommonMark\Node\MathInline;

/**
 * LaTeX's math delimiters, read only when the math.latex_delimiters option is on:
 *
 * - \(…\) is inline math on one line.
 * - \[…\] is display math and may span lines. A paragraph holding only one becomes a block (see MathBlockListener).
 * - A closer is a "\)" or "\]" whose backslash is not itself escaped: the backslashes before it number an odd count.
 *   So "\[a\\]" has no closer, which keeps Laravel's escaped mail values ("\\" for "\") from closing a formula.
 * - Empty or blank TeX, a code span inside the formula, or (inline) a newline before the closer leaves the text to
 *   league/commonmark's escape parser, which turns "\(" into "(" as before.
 *
 * The parser registers above that escape parser, which would otherwise consume "\(" first. Each kind of closer is
 * listed once per paragraph and found by bisection, so a paragraph of unclosed openers parses in linear time.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class LatexInlineParser implements InlineParserInterface, ConfigurationAwareInterface
{
    private ?ConfigurationInterface $config = null;

    /** @var \WeakReference<Cursor>|null The cursor whose paragraph the cached facts below belong to. */
    private ?\WeakReference $cursorRef = null;
    private ?ParagraphScan $scan = null;
    /** @var array<string, list<int>> Offsets of the usable closers, "\)" and "\]" separately. */
    private array $closers = [];

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::oneOf('\(', '\[');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        if ($this->config?->get('math.latex_delimiters') !== true) {
            return false;
        }
        $cursor = $inlineContext->getCursor();
        $text = $cursor->getLine();
        $pos = $cursor->getBytePosition();
        $scan = $this->prepare($cursor, $text);

        $display = $text[$pos + 1] === '[';
        $closer = ParagraphScan::firstAtOrAfter($this->closers[$display ? ']' : ')'], $pos + 2);
        if ($closer === null) {
            return false;
        }
        if (! $display) {
            $newline = $scan->firstNewlineAtOrAfter($pos);
            if ($newline !== null && $newline < $closer) {
                return false;
            }
        }
        $tex = substr($text, $pos + 2, $closer - $pos - 2);
        if (trim($tex) === '' || $scan->hasCodeSpan($pos + 2, strlen($tex))) {
            return false;
        }

        $source = substr($text, $pos, $closer + 2 - $pos);
        $cursor->advanceBy(mb_strlen($source, 'UTF-8'));
        $inlineContext->getContainer()->appendChild(new MathInline($tex, $display, $source));

        return true;
    }

    private function prepare(Cursor $cursor, string $text): ParagraphScan
    {
        if ($this->scan !== null && $this->cursorRef?->get() === $cursor) {
            return $this->scan;
        }
        $this->cursorRef = \WeakReference::create($cursor);
        $this->closers = [')' => [], ']' => []];
        // A backslash run of odd length before ")" or "]" ends in an unescaped backslash: that pair is a closer.
        preg_match_all('/\\\\+(?=[)\]])/', $text, $runs, PREG_OFFSET_CAPTURE);
        foreach ($runs[0] as [$run, $offset]) {
            if (strlen($run) % 2 === 1) {
                $at = $offset + strlen($run) - 1;
                $this->closers[$text[$at + 1]][] = $at;
            }
        }

        return $this->scan = new ParagraphScan($text);
    }
}
