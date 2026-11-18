<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Renderer;

use League\Config\ConfigurationInterface;
use Phactorial\NoteLevel;
use Phactorial\CommonMark\MathOutput;
use Phactorial\MathRender;

/** Turns TeX into the HTML a renderer emits; shared by the block, inline and fenced-code renderers. */
final class MathHtml
{
    private ?MathOutput $output = null;
    private float $size = 16.0;
    private bool $showSource = true;

    public function __construct(private readonly ConfigurationInterface $configuration)
    {
    }

    /**
     * @param string $source The Markdown the formula was read from, delimiters included. It is shown instead of a
     *                       drawing when the reader dropped part of the formula and `on_error` is `source`. Empty, it
     *                       becomes the TeX between `$` (inline) or `$$` (display) delimiters.
     */
    public function render(string $tex, bool $display, bool $block = false, string $source = ''): string
    {
        $this->resolve();
        $math = MathRender::fromTex($tex);
        $math = $display ? $math->display() : $math->inline();
        if ($this->showSource && $this->dropped($math)) {
            $shown = $source !== '' ? $source : ($display ? '$$' . $tex . '$$' : '$' . $tex . '$');
            $code = htmlspecialchars($shown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $block
                ? '<div class="math math-display math-error"><code>' . $code . '</code></div>'
                : '<code class="math math-error">' . $code . '</code>';
        }
        $body = match ($this->output) {
            MathOutput::Mathml => $math->mathml(),
            default => $math->svg($this->size),
        };

        if ($block) {
            return '<div class="math math-display">' . $body . '</div>';
        }

        // Inside a paragraph even display math must be phrasing content; CSS makes .math-display a block.
        return '<span class="math ' . ($display ? 'math-display' : 'math-inline') . '">' . $body . '</span>';
    }

    private function dropped(MathRender $math): bool
    {
        foreach ($math->notes() as $note) {
            if ($note->level === NoteLevel::Dropped) {
                return true;
            }
        }

        return false;
    }

    /** Reads the settings on first use: the configuration is not frozen yet when the extension registers. */
    private function resolve(): void
    {
        if ($this->output !== null) {
            return;
        }
        $this->size = (float) $this->configuration->get('math/size');
        $this->showSource = (string) $this->configuration->get('math/on_error') === 'source';
        $this->output = MathOutput::from((string) $this->configuration->get('math/output'));
    }
}
