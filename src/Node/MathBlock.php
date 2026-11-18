<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Node;

use League\CommonMark\Node\Block\AbstractBlock;

final class MathBlock extends AbstractBlock
{
    /**
     * $source is the Markdown the formula was read from, delimiters included.
     *
     * @internal Engine detail, not public API.
     */
    public function __construct(
        public readonly string $tex,
        public readonly string $source,
    ) {
        parent::__construct();
    }
}
