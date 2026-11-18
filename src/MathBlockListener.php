<?php

declare(strict_types=1);

namespace Phactorial\CommonMark;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;

/**
 * Settles two things only the finished tree can show. GitHub leaves formulas inside link text (and image alt text)
 * as plain text, so those go back to their source. A paragraph that holds nothing but one display formula becomes a
 * MathBlock, so it renders as a block instead of a span inside a paragraph.
 */
final class MathBlockListener
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $inLinks = [];
        $paragraphs = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof MathInline && $this->insideLink($node)) {
                $inLinks[] = $node;
            } elseif ($node instanceof Paragraph) {
                $paragraphs[] = $node;
            }
        }
        foreach ($inLinks as $math) {
            $math->replaceWith(new Text($math->source));
        }
        foreach ($paragraphs as $paragraph) {
            $only = $paragraph->firstChild();
            if ($only instanceof MathInline && $only->display && $only === $paragraph->lastChild()) {
                $block = new MathBlock();
                $block->tex = trim($only->tex);
                $block->source = $only->source;
                $paragraph->replaceWith($block);
            }
        }
    }

    private function insideLink(MathInline $math): bool
    {
        for ($parent = $math->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof Link || $parent instanceof Image) {
                return true;
            }
        }

        return false;
    }
}
