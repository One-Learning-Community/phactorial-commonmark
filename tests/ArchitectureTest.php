<?php

declare(strict_types=1);

namespace Phactorial\CommonMark\Tests;

use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    /** The extension, its node types and its configuration are the public API; the listener, parser, renderers and output enum are the wiring. */
    private const INTERNAL = [
        'MathBlockListener.php', 'MathOutput.php', 'Parser/LatexInlineParser.php', 'Parser/MathInlineParser.php',
        'Parser/ParagraphScan.php', 'Renderer/MathHtml.php', 'Renderer/MathNodeRenderer.php',
    ];

    public function test_engine_types_are_internal_and_the_rest_are_not(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $internal = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match('/\/\*\*(?:(?!\*\/).)*@internal(?:(?!\*\/).)*\*\/\s*(?:final |abstract |readonly )*(?:class|interface|enum|trait) /s', $code) === 1) {
                $internal[] = substr($file->getPathname(), strlen($src) + 1);
            }
        }
        sort($internal);
        $expected = self::INTERNAL;
        sort($expected);
        self::assertSame($expected, $internal);
    }
}
