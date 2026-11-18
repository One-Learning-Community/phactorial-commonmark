<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Node;

use League\CommonMark\Node\Block\AbstractBlock;

final class MathBlock extends AbstractBlock
{
    public string $tex = '';
    /** The Markdown the formula was read from, delimiters included. */
    public string $source = '';
}
