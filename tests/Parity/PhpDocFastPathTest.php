<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Test\Parity\Corpus\ParityCorpus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fast path for simple doc comments, PhpDocSimpleTags, may leave any doc comment to the full parser, PhpDocTagParser.
 * Where it reads one itself, it must read the same tags with the same types.
 */
#[CoversNothing]
final class PhpDocFastPathTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function corpora(): iterable
    {
        foreach (ParityCorpus::NAMES as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    #[DataProvider('corpora')]
    public function testFastPathReadsTheSameTagsAsTheFullParser(string $corpus): void
    {
        $report = new ParityReport();
        foreach (ParityCorpus::inputs($corpus, ParityCorpus::scale()) as $input) {
            $doc = $input->isDocComment();
            if (!$doc) {
                continue;
            }
            ParityCheck::fastPath($input->source, $report);
        }

        static::assertSame([], $report->differences());
    }
}
