<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Test\Parity\Corpus\ParityCorpus;
use Eventjet\Json\Test\Parity\Corpus\TestSuiteDocs;
use Eventjet\Json\Test\Parity\Reference\PhpStanReader;
use Eventjet\Json\Test\Parity\Reference\PhpStanTagSelector;
use Eventjet\Json\Test\Unit\Fixtures\ThrownDecodeError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;

use function sprintf;

/**
 * The decoder reads doc comments like phpstan/phpdoc-parser 2.3.6 as PHPStan 2.3.1 sets it up: the same `@param` and
 * `@var` tags with the same types, and number literals with the same values. The only differences are the number
 * literals that the decoder deliberately rejects, which {@see NumberLiterals::rejection()} lists.
 */
#[CoversNothing]
final class PhpDocParserParityTest extends TestCase
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
    public function testDocCommentsAndTypesReadLikePhpStan(string $corpus): void
    {
        $report = new ParityReport();
        foreach (ParityCorpus::inputs($corpus, ParityCorpus::scale()) as $input) {
            $doc = $input->isDocComment();
            match (true) {
                $input->isType => ParityCheck::type($input->source, $report),
                $doc => ParityCheck::docComment($input->source, $report),
                default => $report->count('skipped, because PHP never returns it as a doc comment'),
            };
        }

        static::assertSame([], $report->differences());
        static::assertGreaterThan(0, $report->compared());
    }

    /** @throws \ReflectionException */
    public function testTestSuiteFieldsResolveLikePhpStan(): void
    {
        $report = new ParityReport();
        foreach (TestSuiteDocs::fields() as $field) {
            $parameter = $field instanceof ReflectionParameter;
            $doc = (string) (
                $field instanceof ReflectionParameter
                    ? $field->getDeclaringFunction()->getDocComment()
                    : $field->getDocComment()
            );
            $tags = PhpStanReader::tags($doc);
            $reference = $parameter
                ? PhpStanTagSelector::parameter($tags, $field->getName())
                : PhpStanTagSelector::property($tags, $field->getName());
            $label = sprintf(
                '%s %s of %s',
                $parameter ? 'parameter' : 'property',
                $field->getName(),
                $field->getDeclaringClass()?->getName() ?? 'a function',
            );
            $ours = ThrownDecodeError::orResult(
                /** @throws \Eventjet\Json\DecodeError */ static fn(): PhpDocType|null => PhpDocFieldType::resolve(
                    $field,
                ),
            );
            ParityCheck::field($label, $ours, $reference, $report);
        }

        static::assertSame([], $report->differences());
        static::assertGreaterThan(0, $report->compared());
    }
}
