<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function getenv;
use function max;
use function str_replace;

/**
 * The doc comments and types that the parity tests compare. Generated corpora use fixed seeds, so every run compares
 * the same inputs. The environment variable `PHPDOC_PARITY_SCALE` multiplies the size of the random corpora; a larger
 * scale compares the same inputs first and then more.
 *
 * @internal
 */
final class ParityCorpus
{
    public const array NAMES = [
        'test suite',
        'examples',
        'grammar',
        'simple',
        'tags',
        'quirks',
        'aliases',
        'gaps',
        'methods',
        'doctrine',
        'tag values',
        'comments',
        'carriage returns',
        'text',
        'random tags',
    ];

    public static function scale(): int
    {
        $scale = getenv('PHPDOC_PARITY_SCALE');
        return $scale === false ? 1 : max(1, (int) $scale);
    }

    /**
     * @return iterable<ParityInput>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function inputs(string $name, int $scale): iterable
    {
        return match ($name) {
            'test suite' => TestSuiteDocs::inputs(),
            'examples' => Examples::inputs(),
            'grammar' => self::grammar(15_000 * $scale),
            'simple' => self::simple(20_000 * $scale),
            'tags' => self::repeat(20_000 * $scale, new TagDocs(new Dice(103))->doc(...)),
            'quirks' => self::repeat(10_000 * $scale, new QuirkDocs(new Dice(104))->doc(...)),
            'aliases' => ProbeDocs::aliases(stride: $scale === 1 ? 2 : 1),
            'gaps' => ProbeDocs::gaps(),
            'methods' => ProbeDocs::methods(),
            'doctrine' => AnnotationProbeDocs::doctrine(),
            'tag values' => AnnotationProbeDocs::tagValues(),
            'comments' => self::comments(2_000 * $scale),
            'carriage returns' => self::repeat(10_000 * $scale, self::randomProbes(107)->carriageReturns(...)),
            'text' => self::repeat(20_000 * $scale, self::randomProbes(108)->text(...)),
            'random tags' => self::repeat(20_000 * $scale, self::randomProbes(109)->tag(...)),
            default => [],
        };
    }

    /** @return iterable<ParityInput> */
    private static function grammar(int $count): iterable
    {
        $dice = new Dice(101);
        $docs = new GrammarDocs($dice);
        for ($index = 0; $index < $count; $index++) {
            $doc = $docs->doc();
            yield ParityInput::doc($doc);
            yield ParityInput::doc($dice->mutate($doc, edits: 3, margin: 0, pool: GrammarDocs::MUTATIONS));
            yield ParityInput::doc(str_replace(search: "\n", replace: " // c\n", subject: $doc));
            yield ParityInput::type($docs->types()->type());
            if (($index % 10) === 0) {
                $deep = $docs->types()->deep();
                yield ParityInput::type($deep);
                yield ParityInput::doc('/** @param ' . $deep . ' $value */');
            }
        }
    }

    /** @return iterable<ParityInput> */
    private static function simple(int $count): iterable
    {
        $docs = new SimpleDocs(new Dice(102));
        for ($index = 0; $index < $count; $index++) {
            $doc = $docs->doc();
            yield ParityInput::doc($doc);
            yield ParityInput::doc($docs->mutate($doc));
        }
    }

    /** @return iterable<ParityInput> */
    private static function comments(int $count): iterable
    {
        $probes = self::randomProbes(106);
        for ($index = 0; $index < $count; $index++) {
            yield from $probes->comments();
        }
    }

    private static function randomProbes(int $seed): RandomProbeDocs
    {
        $dice = new Dice($seed);
        return new RandomProbeDocs($dice, new GrammarDocs($dice));
    }

    /**
     * @param callable(int): string $doc
     * @return iterable<ParityInput>
     */
    private static function repeat(int $count, callable $doc): iterable
    {
        for ($index = 0; $index < $count; $index++) {
            yield ParityInput::doc($doc($index));
        }
    }
}
