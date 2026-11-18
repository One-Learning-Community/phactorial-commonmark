<?php

declare(strict_types=1);

namespace Phactorial\CommonMark;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\Config\ConfigurationBuilderInterface;
use Nette\Schema\Expect;
use Phactorial\CommonMark\Node\MathBlock;
use Phactorial\CommonMark\Node\MathInline;
use Phactorial\CommonMark\Parser\MathInlineParser;
use Phactorial\CommonMark\Renderer\MathHtml;
use Phactorial\CommonMark\Renderer\MathNodeRenderer;

/**
 * GitHub's math syntax in league/commonmark: $…$, $`…`$, $$…$$ and ```math fences, typeset on the server.
 *
 * Configuration lives under the `math` key: `output` ('svg' or 'mathml'), `size` (the SVG font size in pixels) and
 * `on_error` ('source' or 'render').
 */
final class MathExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        $builder->addSchema('math', Expect::structure([
            'output' => Expect::anyOf('svg', 'mathml')->default('svg'),
            // Nette's float() rejects whole numbers, so 'size' => 20 is widened before validation.
            'size' => Expect::float()
                ->min(0.001)
                ->before(static fn (mixed $v): mixed => is_int($v) ? (float) $v : $v)
                ->default(16.0),
            'on_error' => Expect::anyOf('source', 'render')->default('source'),
        ]));
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // The configuration is frozen only after registration, so the renderer reads it on its first render.
        $renderer = new MathNodeRenderer(new MathHtml($environment->getConfiguration()));
        $environment
            ->addInlineParser(new MathInlineParser(), 50)
            ->addEventListener(DocumentParsedEvent::class, new MathBlockListener())
            ->addRenderer(MathInline::class, $renderer)
            ->addRenderer(MathBlock::class, $renderer)
            ->addRenderer(FencedCode::class, $renderer, 10);
    }
}
