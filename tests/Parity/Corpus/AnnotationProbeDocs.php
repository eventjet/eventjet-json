<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function implode;
use function sprintf;

/**
 * Enumerates doc comments that probe how phpdoc-parser ends annotations and the values of known tags, behind the type
 * alias of {@see ProbeDocs::HEAD}.
 *
 * @internal
 */
final class AnnotationProbeDocs
{
    /**
     * Doctrine annotations and constant expressions in `@method` defaults. A tag after a valid annotation on the same
     * line is read, so these show whether an annotation parses.
     *
     * @return iterable<ParityInput>
     */
    public static function doctrine(): iterable
    {
        $tail = ",\n * @var(int)\n * )\n */";
        foreach (DoctrineValues::FORMATS as $format) {
            foreach (DoctrineValues::VALUES as $value) {
                $arguments = sprintf($format, $value);
                foreach (['@Foo', '@foo', '@Foo\\Bar', '@psalm-foo'] as $tag) {
                    yield ParityInput::doc(ProbeDocs::HEAD . $tag . '(' . $arguments . $tail);
                    yield ParityInput::doc(
                        "/**\n * text " . $tag . '(' . $arguments . ") @param list<int> \$value\n */",
                    );
                }
            }
        }
        foreach (DoctrineValues::EXPRESSIONS as $expression) {
            yield ParityInput::doc(ProbeDocs::HEAD . '@method foo(int $a = ' . $expression . ")\n */");
            yield ParityInput::doc(ProbeDocs::HEAD . '@method foo($a = ' . $expression . ", \$b)\n */");
            yield ParityInput::doc(ProbeDocs::HEAD . '@var ' . $expression . "\n */");
            yield ParityInput::doc(ProbeDocs::HEAD . '@var callable(): ' . $expression . "\n */");
            yield ParityInput::doc(ProbeDocs::HEAD . '@var Closure(): ' . $expression . "|int\n */");
        }
    }

    /**
     * Every tag that phpdoc-parser knows, with many values, behind the type alias.
     *
     * @return iterable<ParityInput>
     */
    public static function tagValues(): iterable
    {
        foreach (Product::of(TagValues::TAGS, TagValues::VALUES) as $parts) {
            $line = implode(' ', $parts);
            yield ParityInput::doc(ProbeDocs::HEAD . $line . "\n */");
            yield ParityInput::doc(ProbeDocs::HEAD . $line . "\n * \n */");
        }
    }
}
