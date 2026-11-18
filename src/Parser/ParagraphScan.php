<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Parser;

/**
 * Facts about one paragraph's text that the inline parsers ask for many times: where its newlines are, and where
 * code spans open. Each list is built once, when first needed, and searched by bisection, so a paragraph of many
 * openers never rescans its text.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class ParagraphScan
{
    /** @var list<int>|null */
    private ?array $newlines = null;

    /** @var list<int>|null Offsets of the backtick runs that open a code span; built when first needed. */
    private ?array $codeOpeners = null;

    public function __construct(private readonly string $text)
    {
    }

    /** The offset of the first newline at or after $from, or null. */
    public function firstNewlineAtOrAfter(int $from): ?int
    {
        return self::firstAtOrAfter($this->newlines(), $from);
    }

    /** Whether a code span opens inside the $length bytes of formula starting at $from. */
    public function hasCodeSpan(int $from, int $length): bool
    {
        $opener = self::firstAtOrAfter($this->codeOpeners(), $from);

        return $opener !== null && $opener < $from + $length;
    }

    /**
     * The smallest value in the sorted list that is at least $from, or null.
     *
     * @param list<int> $sorted
     */
    public static function firstAtOrAfter(array $sorted, int $from): ?int
    {
        $low = 0;
        $high = count($sorted);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            if ($sorted[$mid] < $from) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $sorted[$low] ?? null;
    }

    /** @return list<int> */
    private function newlines(): array
    {
        if ($this->newlines !== null) {
            return $this->newlines;
        }
        $newlines = [];
        for ($n = strpos($this->text, "\n"); $n !== false; $n = strpos($this->text, "\n", $n + 1)) {
            $newlines[] = $n;
        }

        return $this->newlines = $newlines;
    }

    /** @return list<int> Offsets of the backtick runs that open a code span, ascending. */
    private function codeOpeners(): array
    {
        if ($this->codeOpeners !== null) {
            return $this->codeOpeners;
        }
        preg_match_all('/`+/', $this->text, $runs, PREG_OFFSET_CAPTURE);
        $runs = $runs[0];
        $byLength = [];
        foreach ($runs as $i => [$run]) {
            $byLength[strlen($run)][] = $i;
        }
        $pointers = [];
        $openers = [];
        for ($i = 0, $count = count($runs); $i < $count; $i++) {
            $length = strlen($runs[$i][0]);
            $pointers[$length] ??= 0;
            while (isset($byLength[$length][$pointers[$length]]) && $byLength[$length][$pointers[$length]] <= $i) {
                $pointers[$length]++;
            }
            $closer = $byLength[$length][$pointers[$length]] ?? null;
            if ($closer !== null) {
                $openers[] = $runs[$i][1];
                $i = $closer;
            }
        }

        return $this->codeOpeners = $openers;
    }
}
