<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests\Support;

use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\Config\ConfigurationInterface;

/** Records the inline parsers, renderers and listeners an extension registers; every other registration throws. */
final class RecordingBuilder implements EnvironmentBuilderInterface
{
    /** @var list<array{string, int}> */
    public array $parsers = [];
    /** @var list<array{string, int}> */
    public array $renderers = [];
    /** @var list<array{string, int}> */
    public array $listeners = [];

    public function getConfiguration(): ConfigurationInterface
    {
        return (new Environment(['math' => []]))->getConfiguration();
    }

    public function addInlineParser(InlineParserInterface $parser, int $priority = 0): EnvironmentBuilderInterface
    {
        $this->parsers[] = [$parser::class, $priority];

        return $this;
    }

    public function addRenderer(string $nodeClass, NodeRendererInterface $renderer, int $priority = 0): EnvironmentBuilderInterface
    {
        $this->renderers[] = [$nodeClass, $priority];

        return $this;
    }

    public function addEventListener(string $eventClass, callable $listener, int $priority = 0): EnvironmentBuilderInterface
    {
        $this->listeners[] = [$eventClass, $priority];

        return $this;
    }

    public function addExtension(ExtensionInterface $extension): EnvironmentBuilderInterface
    {
        throw new \LogicException(__FUNCTION__);
    }

    public function addBlockStartParser(BlockStartParserInterface $parser, int $priority = 0): EnvironmentBuilderInterface
    {
        throw new \LogicException(__FUNCTION__);
    }

    public function addDelimiterProcessor(DelimiterProcessorInterface $processor): EnvironmentBuilderInterface
    {
        throw new \LogicException(__FUNCTION__);
    }
}
