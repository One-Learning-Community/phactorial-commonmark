<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;

final class HighlighterTest extends TestCase
{
    #[TestWith([true])]
    #[TestWith([false])]
    public function test_a_math_fence_wins_over_a_code_highlighter(bool $highlighterFirst): void
    {
        $highlighter = new class implements NodeRendererInterface {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
            {
                return '<pre class="highlighted"></pre>';
            }
        };

        $environment = new Environment(['math' => ['output' => 'mathml']]);
        $environment->addExtension(new CommonMarkCoreExtension());
        if ($highlighterFirst) {
            $environment->addRenderer(FencedCode::class, $highlighter, 10);
        }
        $environment->addExtension(new MathExtension());
        if (!$highlighterFirst) {
            $environment->addRenderer(FencedCode::class, $highlighter, 10);
        }

        $html = (new MarkdownConverter($environment))->convert("```math\na^2\n```\n\n```php\necho 1;\n```\n")->getContent();

        self::assertStringContainsString('<div class="math math-display"><math', $html);
        self::assertSame(1, substr_count($html, '<pre class="highlighted"></pre>'), $html);
    }
}
