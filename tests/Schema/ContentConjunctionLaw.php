<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\ValidationFailure;
use Eventjet\Json\Schema\Validator;
use JsonException;
use PHPUnit\Framework\Assert;
use stdClass;

use function array_key_exists;
use function json_decode;
use function json_encode;
use function sort;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * A content keyword added to a schema that has none fails on its own: the value fails the result as it fails the
 * schema, plus as it fails a schema with only the content keyword. Content keywords depend on no other keyword, and
 * draft 7 asserts them.
 *
 * @internal
 */
final class ContentConjunctionLaw
{
    private const array CONTENT = ['contentMediaType' => 'application/json', 'contentEncoding' => 'base64'];

    /** @var array{string, string, array{assertFormats?: true}}|null The schema, class and options of the cache. */
    private static array|null $compiledFor = null;

    /** @var array<string, array{Validator, Validator}> The schema with each keyword, and the keyword alone. */
    private static array $validators = [];

    /**
     * @param list<ValidationFailure> $failures The failures of the value against the schema.
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    public static function assertHolds(OfficialSuiteSchema $schema, mixed $value, array $failures): void
    {
        foreach (self::validators($schema) as $keyword => [$combined, $alone]) {
            $expected = [...$failures, ...($alone->validateValue($value)->failures ?? [])];
            $actual = $combined->validateValue($value)->failures ?? [];
            Assert::assertSame(self::rows($expected), self::rows($actual), $keyword);
        }
    }

    /**
     * @return array<string, array{Validator, Validator}>
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    private static function validators(OfficialSuiteSchema $schema): array
    {
        $key = [$schema->json, $schema->class, $schema->options];
        if (self::$compiledFor === $key) {
            return self::$validators;
        }
        self::$compiledFor = $key;
        self::$validators = [];
        $selfContained = SelfContainedSchemas::is($schema->json);
        /** @var mixed $document */
        $document = json_decode($schema->json, flags: JSON_THROW_ON_ERROR);
        if (!$selfContained || !$document instanceof stdClass) {
            return [];
        }
        $members = (array) $document;
        $alone = array_key_exists('$schema', $members) ? ['$schema' => $members['$schema']] : [];
        foreach (self::CONTENT as $keyword => $content) {
            if (array_key_exists($keyword, $members)) {
                return self::$validators = [];
            }
            self::$validators[$keyword] = [
                self::compile($schema, [...$members, $keyword => $content]),
                self::compile($schema, [...$alone, $keyword => $content]),
            ];
        }
        return self::$validators;
    }

    /**
     * @param array<array-key, mixed> $members
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    private static function compile(OfficialSuiteSchema $schema, array $members): Validator
    {
        $json = json_encode((object) $members, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $document = JsonSchema::decode($json, $schema->class);
        Assert::assertNotInstanceOf(DecodeError::class, $document, $json);
        $validator = Validator::compile($document, ...$schema->options);
        Assert::assertInstanceOf(Validator::class, $validator, $json);
        return $validator;
    }

    /**
     * @param list<ValidationFailure> $failures
     * @return list<string> The failures in a form that `assertSame` compares regardless of their order.
     * @throws JsonException
     */
    private static function rows(array $failures): array
    {
        $rows = [];
        foreach ($failures as $failure) {
            $rows[] = json_encode([
                $failure->instanceLocation,
                $failure->keywordLocation,
                $failure->absoluteKeywordLocation,
                $failure->message,
            ], JSON_THROW_ON_ERROR);
        }
        sort($rows);
        return $rows;
    }
}
