<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use League\Config\Configuration;
use Phactorial\CommonMark\MathExtension;
use Phactorial\CommonMark\Renderer\MathHtml;
use PHPUnit\Framework\TestCase;

final class MathHtmlTest extends TestCase
{
    private function html(): MathHtml
    {
        $configuration = new Configuration();
        (new MathExtension())->configureSchema($configuration);

        return new MathHtml($configuration->reader());
    }

    public function test_an_empty_source_falls_back_to_the_tex_between_inline_delimiters(): void
    {
        self::assertSame('<code class="math math-error">$\nosuchcommand$</code>', $this->html()->render('\nosuchcommand', false));
    }

    public function test_an_empty_source_falls_back_to_the_tex_between_display_delimiters(): void
    {
        self::assertSame(
            '<div class="math math-display math-error"><code>$$\nosuchcommand$$</code></div>',
            $this->html()->render('\nosuchcommand', true, true),
        );
    }
}
