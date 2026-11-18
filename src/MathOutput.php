<?php

declare(strict_types=1);

namespace Phactorial\CommonMark;

/**
 * How a formula is drawn.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
enum MathOutput: string
{
    /** Inline SVG drawn in STIX Two Math; looks the same in every browser, mail client and feed reader. */
    case Svg = 'svg';
    /** MathML Core; the browser draws it in its own math font, and it stays selectable text. */
    case Mathml = 'mathml';
}
