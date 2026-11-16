<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\SchemaError;
use Eventjet\Json\Schema\Validator;
use JsonException;
use PHPUnit\Framework\Assert;
use stdClass;

use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_contains;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * A keyword that the validator reads, given a value of the wrong JSON type, makes compiling fail with an error that
 * names the keyword. The draft classes reject such values while decoding, so the law moves the schema under an
 * unknown keyword, `x`, which keeps its value as it is, and refers to it with `$ref`. The wrong values are `null`, and
 * an array that holds `null`: no keyword takes either, except `const`, which takes anything, and `enum`, which takes
 * any array. {@see ReadKeywords} lists the keywords that the validator reads.
 *
 * @internal
 */
final class MalformedKeywordLaw
{
    private const array META_SCHEMAS = [
        'http://json-schema.org/draft-04/schema#' => Draft4::class,
        'http://json-schema.org/draft-06/schema#' => Draft6::class,
        'http://json-schema.org/draft-07/schema#' => Draft7::class,
        'https://json-schema.org/draft/2019-09/schema' => Draft201909::class,
        'https://json-schema.org/draft/2020-12/schema' => Draft202012::class,
    ];

    /** @var array{string, string, array{assertFormats?: true}}|null The schema, class and options last checked. */
    private static array|null $checked = null;

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    public static function assertHolds(OfficialSuiteSchema $schema): void
    {
        $key = [$schema->json, $schema->class, $schema->options];
        if (self::$checked === $key) {
            return;
        }
        self::$checked = $key;
        $selfContained = SelfContainedSchemas::is($schema->json);
        /** @var mixed $document */
        $document = json_decode($schema->json, flags: JSON_THROW_ON_ERROR);
        if (!$selfContained || !$document instanceof stdClass) {
            return;
        }
        $members = (array) $document;
        /** @var mixed $metaSchema */
        $metaSchema = $members['$schema'] ?? null;
        unset($members['$schema']);
        $class = is_string($metaSchema) ? self::META_SCHEMAS[$metaSchema] ?? null : $schema->class;
        if ($class === null) {
            return;
        }
        $root = is_string($metaSchema) ? ['$schema' => $metaSchema] : [];
        foreach ($members as $keyword => $_) {
            if (!ReadKeywords::has($class, $keyword, $members)) {
                continue;
            }
            self::assertWrongValuesFail($schema, $root, $members, (string) $keyword);
        }
    }

    /**
     * @param array<string, mixed> $root The members of the root other than the reference to the moved schema.
     * @param array<array-key, mixed> $members
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    private static function assertWrongValuesFail(
        OfficialSuiteSchema $schema,
        array $root,
        array $members,
        string $keyword,
    ): void {
        foreach ([null, [null]] as $wrong) {
            if ($keyword === 'enum' && $wrong !== null) {
                continue;
            }
            $moved = $members;
            $moved[$keyword] = $wrong;
            $json = json_encode(
                (object) [...$root, '$ref' => '#/x', 'x' => (object) $moved],
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            );
            self::assertNamesKeyword($schema, $json, $keyword);
        }
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    private static function assertNamesKeyword(OfficialSuiteSchema $schema, string $json, string $keyword): void
    {
        $document = JsonSchema::decode($json, $schema->class);
        Assert::assertNotInstanceOf(DecodeError::class, $document, $json);
        $error = Validator::compile($document, ...$schema->options);
        Assert::assertInstanceOf(SchemaError::class, $error, $json);
        $at = ' at #/x/' . $keyword;
        $message = $error->getMessage();
        $named = str_contains($message . ' ', $at . ' ') || str_contains($message, $at . '/');
        Assert::assertTrue($named, sprintf('%s: %s', $json, $message));
    }
}
