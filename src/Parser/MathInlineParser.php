<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Phactorial\CommonMark\Node\MathInline;

/**
 * GitHub's math, as its renderer treats it (the rules are pinned by tests/fixtures/github):
 *
 * - $`…`$ may span lines and may follow any character.
 * - $…$ stays on one line. The opener is not followed by white space and not preceded by a letter, digit, underscore
 *   or dollar sign. The closer is not preceded by a backslash and not followed by a letter, digit or underscore, so
 *   "costs $5 and $10" and "$a$b$" stay text. Inner white space before the closer is fine.
 * - $$…$$ follows the same rules on one line and is inline math. When the whole paragraph starts and ends with $$,
 *   every $$…$$ in it is display math, may span lines, may touch white space and ignores the follower rule; a paragraph
 *   holding only one display formula becomes a block (see MathBlockListener).
 * - A backslash-escaped dollar never closes a formula, and a code span inside the formula, which GitHub reads first,
 *   ends the attempt. A closer may be followed by a non-ASCII letter, but an opener may not be preceded by one.
 *
 * An opener must not rescan the paragraph, or a paragraph of unclosed openers parses in quadratic time. The usable
 * "$$" closers, the newlines and the code-span openers are each listed once per paragraph, and every opener finds
 * its closer, and any newline or code span in between, by binary search. A single-dollar formula cannot contain a
 * dollar sign, so its scan ends at the next one.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class MathInlineParser implements InlineParserInterface
{
    /** What may not follow a closer: an ASCII letter, digit or underscore. GitHub lets "é" follow. */
    private const string WORD = '[A-Za-z0-9_]';
    /** What may not precede an opener: the same, or any non-ASCII byte, because GitHub rejects "é$x$". */
    private const string BEFORE = '[A-Za-z0-9_\x80-\xFF]';

    /** @var \WeakReference<Cursor>|null The cursor whose paragraph the cached facts below belong to. */
    private ?\WeakReference $cursorRef = null;
    private int $lastBacktickCloser = -1;
    private bool $displayMode = false;
    /** @var list<int>|null Offsets of the usable "$$" closers; built when first needed. */
    private ?array $closers = null;
    private ?ParagraphScan $scan = null;

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $text = $cursor->getLine();
        $pos = $cursor->getBytePosition();
        $this->prepare($cursor, $text);

        $found = $this->match($text, $pos);
        if ($found === null) {
            return false;
        }
        [$length, $tex, $display] = $found;
        $source = substr($text, $pos, $length);
        $cursor->advanceBy(mb_strlen($source, 'UTF-8'));
        $inlineContext->getContainer()->appendChild(new MathInline($tex, $display, $source));

        return true;
    }

    /** @return array{int, string, bool}|null The matched byte length, the TeX and whether it is display math. */
    private function match(string $text, int $pos): ?array
    {
        $next = $text[$pos + 1] ?? '';
        $prev = $pos > 0 ? $text[$pos - 1] : '';
        $afterWord = $prev !== '' && preg_match('/' . self::BEFORE . '/', $prev) === 1;

        if ($next === '`' && $pos + 2 <= $this->lastBacktickCloser
            && preg_match('/\G\$`(.+?)`\$/s', $text, $m, 0, $pos) === 1) {
            return [strlen($m[0]), $m[1], false];
        }

        if ($next === '$') {
            return $afterWord ? null : $this->matchDouble($text, $pos);
        }

        if ($afterWord || $prev === '$' || $next === '' || ctype_space($next)) {
            return null;
        }
        if (preg_match('/\G\$([^$\n]+)(?<!\\\\)\$(?!' . self::WORD . ')/', $text, $m, 0, $pos) !== 1
            || $this->scan()->hasCodeSpan($pos + 1, strlen($m[1]))) {
            return null;
        }

        return [strlen($m[0]), $m[1], false];
    }

    /** @return array{int, string, bool}|null */
    private function matchDouble(string $text, int $pos): ?array
    {
        if (! $this->displayMode && ctype_space($text[$pos + 2] ?? ' ')) {
            return null;
        }
        $closer = ParagraphScan::firstAtOrAfter($this->closers($text), $pos + 3);
        if ($closer === null) {
            return null;
        }
        if (! $this->displayMode) {
            $newline = $this->scan()->firstNewlineAtOrAfter($pos);
            if ($newline !== null && $newline < $closer) {
                return null;
            }
        }
        $tex = substr($text, $pos + 2, $closer - $pos - 2);
        if ($this->scan()->hasCodeSpan($pos + 2, strlen($tex))) {
            return null;
        }

        return [$closer + 2 - $pos, $tex, $this->displayMode];
    }

    private function prepare(Cursor $cursor, string $text): void
    {
        if ($this->cursorRef?->get() === $cursor) {
            return;
        }
        $this->cursorRef = \WeakReference::create($cursor);
        $position = strrpos($text, '`$');
        $this->lastBacktickCloser = $position === false ? -1 : $position;
        $this->scan = new ParagraphScan($text);
        $this->closers = null;
        $this->displayMode = str_starts_with($text, '$$') && str_ends_with(rtrim($text), '$$');
    }

    /**
     * Offsets of every "$$" that can close a formula: not after a backslash and, outside display mode, not before an
     * ASCII letter, digit or underscore. Built once per paragraph, so each opener finds its closer by binary search.
     *
     * @return list<int>
     */
    private function closers(string $text): array
    {
        if ($this->closers !== null) {
            return $this->closers;
        }
        $closers = [];
        $length = strlen($text);
        for ($q = strpos($text, '$$'); $q !== false; $q = strpos($text, '$$', $q + 1)) {
            if ($q === 0 || $text[$q - 1] === '\\') {
                continue;
            }
            if (! $this->displayMode && $q + 2 < $length && preg_match('/' . self::WORD . '/', $text[$q + 2]) === 1) {
                continue;
            }
            $closers[] = $q;
        }

        return $this->closers = $closers;
    }

    private function scan(): ParagraphScan
    {
        return $this->scan ?? throw new \LogicException('parse() prepares the scan first.');
    }
}
