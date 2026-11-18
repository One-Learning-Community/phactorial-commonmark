<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Node;

use League\CommonMark\Node\Inline\AbstractInline;

final class MathInline extends AbstractInline
{
    /**
     * $source is the Markdown the formula was read from, delimiters included, for places that keep it as text.
     *
     * @internal Engine detail, not public API.
     */
    public function __construct(
        public readonly string $tex,
        public readonly bool $display = false,
        public readonly string $source = '',
    ) {
        parent::__construct();
    }
}
