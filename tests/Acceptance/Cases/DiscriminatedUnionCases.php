<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Cases\Discriminated\Document;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Codes;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\NumericCode;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\TextCode;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\ZeroB;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\ZeroTagged;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Parity\Corpus\Dice;
use JsonException;
use ReflectionException;
use RuntimeException;

use function json_encode;
use function sprintf;
use function strtolower;
use function var_export;

use const JSON_THROW_ON_ERROR;

/**
 * Unions of several classes whose objects name their class in a member. Each fixture set generates documents from a
 * fixed seed. Every document round-trips, and a document whose discriminator is missing or has a value that no class
 * declares is rejected.
 *
 * @internal
 */
final class DiscriminatedUnionCases
{
    private const int DOCUMENTS = 8;

    private const array SETS = ['flat', 'mixed', 'enum tags', 'aliases', 'named', 'recursive'];

    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'integer and numeric string discriminators select different classes' => [
            new Codes([new NumericCode(), new TextCode(), new NumericCode()]),
        ];
        yield 'discriminator with an integer-like JSON name selects the class' => [new ZeroTagged(new ZeroB())];
        yield 'union names one class in two spellings' => [CollectionDeclarationFixture::object(
            'array',
            'list<\\' . Person::class . '|\\' . strtolower(Person::class) . '>',
            'param',
            [new Person('Ada', 'Lovelace')],
        )];
        foreach (self::SETS as $set) {
            $dice = new Dice(87);
            for ($index = 0; $index < self::DOCUMENTS; ++$index) {
                yield sprintf('discriminated %s union document %d round-trips', $set, $index) => [
                    new Document($set, $dice)->root,
                ];
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws JsonException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::SETS as $set) {
            $dice = new Dice(113);
            for ($index = 0; $index < self::DOCUMENTS; ++$index) {
                $prefix = sprintf('discriminated %s union document %d rejects ', $set, $index);
                yield $prefix . 'an undeclared tag' => self::undeclaredTag(new Document($set, $dice));
                yield $prefix . 'a missing tag' => self::missingTag(new Document($set, $dice));
            }
        }
        yield from DiscriminatedValueErrorCases::errors();
        foreach (DiscriminatedDeclarationErrorCases::errors() as $label => [$class, $message]) {
            yield $label => ['{}', $class, $message, DecodeErrorKind::UnsupportedDeclaration];
        }
    }

    /**
     * @return array{string, class-string, string, DecodeErrorKind}
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function undeclaredTag(Document $document): array
    {
        $tagged = $document->pickTagged();
        $tagged->replaceTag();
        return [
            json_encode($document->root, JSON_THROW_ON_ERROR),
            $document->root::class,
            sprintf(
                'Could not create %s from the JSON object: Field %s must be one of %s; %s given.',
                $tagged->container,
                Document::memberPath($tagged->path, $tagged->tag->member),
                $tagged->tag->allowed,
                var_export($tagged->undeclared(), return: true),
            ),
            DecodeErrorKind::InvalidValue,
        ];
    }

    /**
     * @return array{string, class-string, string, DecodeErrorKind}
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function missingTag(Document $document): array
    {
        $tagged = $document->pickTagged();
        $tagged->replaceTag();
        return [
            $tagged->withoutTag(json_encode($document->root, JSON_THROW_ON_ERROR)),
            $document->root::class,
            sprintf(
                'Could not create %s from the JSON object: Field %s has no member %s, which must be one of %s.',
                $tagged->container,
                $tagged->path,
                $tagged->tag->member,
                $tagged->tag->allowed,
            ),
            DecodeErrorKind::InvalidValue,
        ];
    }
}
