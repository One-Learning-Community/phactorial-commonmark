<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Phactorial\CommonMark\MathExtension;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;
use Phactorial\CommonMark\Parser\LatexInlineParser;
use Phactorial\CommonMark\Parser\MathInlineParser;
use Phactorial\CommonMark\Tests\Support\RecordingBuilder;

/** Pins the registration contract the extension has with league/commonmark: what it registers, at which priority, and the HTML it emits. */
final class ContractTest extends TestCase
{
    public function test_priorities_are_the_documented_ones(): void
    {
        $builder = new RecordingBuilder();
        (new MathExtension())->register($builder);

        self::assertSame([[MathInlineParser::class, 50], [LatexInlineParser::class, 90]], $builder->parsers);
        self::assertSame(
            [[MathInline::class, 0], [MathBlock::class, 0], [FencedCode::class, 50]],
            $builder->renderers,
        );
        self::assertSame([[DocumentParsedEvent::class, 0]], $builder->listeners);
    }

    public function test_html_shape(): void
    {
        self::assertStringStartsWith('<p><span class="math math-inline"><math', $this->convert('$a$'));
        self::assertStringStartsWith('<div class="math math-display"><math', $this->convert("\$\$a\$\$\n"));
        self::assertStringContainsString('<code class="math math-error">$\nosuch$</code>', $this->convert('$\nosuch$'));
        self::assertStringContainsString('<pre><code class="language-js">', $this->convert("```js\nlet a = 1;\n```\n"));
    }

    private function convert(string $markdown): string
    {
        $environment = new Environment(['math' => ['output' => 'mathml']]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new MathExtension());

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
