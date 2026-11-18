<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;

/**
 * Renders math nodes, and ```math fences; returns null for any other fence so the stock renderer takes it.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class MathNodeRenderer implements NodeRendererInterface
{
    public function __construct(private MathHtml $html)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        return match (true) {
            $node instanceof MathInline => $this->html->render($node->tex, $node->display, false, $node->source),
            $node instanceof MathBlock => $this->html->render($node->tex, true, true, $node->source),
            $node instanceof FencedCode && ($node->getInfoWords()[0] ?? '') === 'math' => $this->fence($node),
            default => null,
        };
    }

    private function fence(FencedCode $node): string
    {
        $tex = trim($node->getLiteral());

        return $this->html->render($tex, true, true, '$$' . $tex . '$$');
    }
}
