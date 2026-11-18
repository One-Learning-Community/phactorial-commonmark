<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests\Support;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;

/** Writes each formula as an empty <span data-kind data-tex> so a test can compare segments without typesetting. */
final class SegmentRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        return match (true) {
            $node instanceof MathInline => self::span($node->display ? 'display' : 'inline', $node->tex),
            $node instanceof MathBlock => self::span('display', $node->tex),
            $node instanceof FencedCode && ($node->getInfoWords()[0] ?? '') === 'math' => self::span('display', $node->getLiteral()),
            default => null,
        };
    }

    private static function span(string $kind, string $tex): string
    {
        return '<span data-kind="' . $kind . '" data-tex="' . htmlspecialchars($tex, ENT_QUOTES) . '"></span>';
    }
}
