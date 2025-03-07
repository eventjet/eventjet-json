<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Type\JsonType;
use Eventjet\Json\Type\Member;
use Eventjet\Json\Type\ValidationIssue;
use Eventjet\Json\Type\ValidationResult;
use PHPUnit\Framework\TestCase;

use function count;
use function implode;
use function sprintf;

final class TypesTest extends TestCase
{
    /**
     * @return iterable<string, array{JsonType | callable(): JsonType, string, list<ValidationIssue>}>
     */
    public static function validateCases(): iterable
    {
        $cases = [
            [JsonType::string(), '"foo"', []],
            [JsonType::string(), '""', []],
            [JsonType::string(), '42', [new ValidationIssue('Expected string, got 42.', '')]],
            [
                JsonType::string(),
                '{"name": "John", "age": 22}',
                [new ValidationIssue('Expected string, got {"name":"John","age":22}.', '')],
            ],

            [static fn(): JsonType => JsonType::number(), '42', []],
            [JsonType::number(), '3.14', []],
            [JsonType::number(), '"42"', [new ValidationIssue('Expected number, got "42".', '')]],

            [static fn(): JsonType => JsonType::array(JsonType::string()), '["foo", "bar"]', []],
            [JsonType::array(JsonType::string()), '[]', []],
            [JsonType::array(JsonType::string()), '[42]', [new ValidationIssue('Expected string, got 42.', '0')]],

            [static fn(): JsonType => JsonType::boolean(), 'true', []],
            [JsonType::boolean(), 'false', []],
            [JsonType::boolean(), '"true"', [new ValidationIssue('Expected boolean, got string.', '')]],

            [JsonType::true(), 'true', []],
            [JsonType::true(), 'false', [new ValidationIssue('Expected true, got false.', '')]],

            [JsonType::false(), 'false', []],
            [JsonType::false(), 'true', [new ValidationIssue('Expected false, got true.', '')]],

            [JsonType::null(), 'null', []],
            [JsonType::null(), 'false', [new ValidationIssue('Expected null, got false.', '')]],

            [static fn(): JsonType => JsonType::true()->or(JsonType::false()), 'true', []],
            [static fn(): JsonType => JsonType::union(JsonType::string(), JsonType::null()), '"foo"', []],
            [JsonType::union(JsonType::string(), JsonType::null()), 'null', []],
            [
                JsonType::union(JsonType::string(), JsonType::null()),
                '42',
                [new ValidationIssue('Expected null or string, got 42.', '')],
            ],

            [
                static fn(): JsonType => JsonType::object(['name' => Member::required(JsonType::string())]),
                '{"name": "John"}',
                [],
            ],
            [static fn(): JsonType => JsonType::object(['name' => Member::optional(JsonType::string())]), '{}', []],
            [
                JsonType::object(['name' => Member::required(JsonType::string())]),
                '{}',
                [new ValidationIssue('Missing required member.', 'name')],
            ],
            [
                JsonType::object(['name' => Member::required(JsonType::string())]),
                '{"name": 42}',
                [new ValidationIssue('Expected string, got 42.', 'name')],
            ],
            [
                JsonType::object(['name' => Member::optional(JsonType::string())]),
                '{"name": 42}',
                [new ValidationIssue('Expected string, got 42.', 'name')],
            ],
            [
                JsonType::object([
                    'a' => Member::optional(JsonType::string()),
                    'b' => Member::required(JsonType::number()),
                ]),
                '{"a": "foo"}',
                [new ValidationIssue('Missing required member.', 'b')],
            ],
            [
                JsonType::object([
                    'a' => Member::optional(JsonType::string()),
                    'b' => Member::required(JsonType::number()),
                ]),
                '{}',
                [new ValidationIssue('Missing required member.', 'b')],
            ],
            [
                JsonType::object([
                    'name' => Member::optional(JsonType::string()),
                    'age' => Member::required(JsonType::number()),
                ]),
                '["John", 42]',
                [new ValidationIssue('Expected object, got ["John",42].', '')],
            ],
        ];
        foreach ($cases as [$type, $json, $expected]) {
            yield sprintf('%s, %s', $type instanceof JsonType ? $type : $type(), $json) => [$type, $json, $expected];
        }
    }

    /**
     * @return iterable<string, array{JsonType, string}>
     */
    public static function toStringCases(): iterable
    {
        yield 'String' => [JsonType::string(), 'string'];
        yield 'Number' => [JsonType::number(), 'number'];
        yield 'Boolean' => [JsonType::boolean(), 'boolean'];
        yield 'True' => [JsonType::true(), 'true'];
        yield 'False' => [JsonType::false(), 'false'];
        yield 'Null' => [JsonType::null(), 'null'];
        yield 'String array' => [JsonType::array(JsonType::string()), 'Array<string>'];
        yield 'Union with 2 types' => [JsonType::union(JsonType::string(), JsonType::null()), 'string | null'];
        yield 'Union with 3 types' => [
            JsonType::union(JsonType::string(), JsonType::null(), JsonType::number()),
            'string | null | number',
        ];
        yield 'Object with 1 required member' => [
            JsonType::object(['name' => Member::required(JsonType::string())]),
            '{name: string}',
        ];
        yield 'Object with 1 optional member' => [
            JsonType::object(['name' => Member::optional(JsonType::string())]),
            '{name?: string}',
        ];
        yield 'Object with 2 members' => [
            JsonType::object([
                'name' => Member::required(JsonType::string()),
                'age' => Member::optional(JsonType::number()),
            ]),
            '{name: string, age?: number}',
        ];
    }

    /**
     * @return iterable<string, array{JsonType, JsonType, bool}>
     */
    public static function equalsCases(): iterable
    {
        yield 'Object member order does not matter' => [
            JsonType::object([
                'name' => Member::required(JsonType::string()),
                'age' => Member::optional(JsonType::number()),
            ]),
            JsonType::object([
                'age' => Member::optional(JsonType::number()),
                'name' => Member::required(JsonType::string()),
            ]),
            true,
        ];
        yield 'Union order does not matter' => [
            JsonType::union(JsonType::string(), JsonType::null()),
            JsonType::union(JsonType::null(), JsonType::string()),
            true,
        ];
        yield 'Union order in array does not matter' => [
            JsonType::array(JsonType::union(JsonType::string(), JsonType::null())),
            JsonType::array(JsonType::union(JsonType::null(), JsonType::string())),
            true,
        ];
        yield 'Union order in object member does not matter' => [
            JsonType::object([
                'name' => Member::required(JsonType::union(JsonType::string(), JsonType::null())),
            ]),
            JsonType::object([
                'name' => Member::required(JsonType::union(JsonType::null(), JsonType::string())),
            ]),
            true,
        ];
        yield 'One object requires a member, the other does not' => [
            JsonType::object(['name' => Member::required(JsonType::string())]),
            JsonType::object(['name' => Member::optional(JsonType::string())]),
            false,
        ];
    }

    /**
     * @param list<ValidationIssue> $issues
     */
    private static function arrayContainsIssue(array $issues, ValidationIssue $expected): bool
    {
        foreach ($issues as $issue) {
            if (!$issue->equals($expected)) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * @param list<ValidationIssue> $issues
     */
    private static function assertIssueInList(ValidationIssue $expected, array $issues): void
    {
        self::assertTrue(
            self::arrayContainsIssue($issues, $expected),
            sprintf('Expected issue %s not found.', $expected),
        );
    }

    /**
     * @param list<ValidationIssue> $expectedIssues
     */
    private static function expectIssues(ValidationResult $actual, array $expectedIssues): void
    {
        self::assertFalse($actual->isValid(), 'Expected validation to fail, but it succeeded.');
        self::assertCount(
            count($expectedIssues),
            $actual->issues,
            sprintf('Expected %d issues, but got %d.', count($expectedIssues), count($actual->issues)),
        );

        foreach ($expectedIssues as $expectedIssue) {
            self::assertIssueInList($expectedIssue, $actual->issues);
        }
    }

    /**
     * @param JsonType | callable(): JsonType $type
     * @param list<ValidationIssue> $expectedIssues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validateCases')]
    public function testValidate(JsonType|callable $type, string $json, array $expectedIssues = []): void
    {
        if (!$type instanceof JsonType) {
            $type = $type();
        }

        $result = $type->validate($json);

        if ($expectedIssues === []) {
            self::assertTrue(
                $result->isValid(),
                sprintf(
                    'Expected %s to validate against %s, but got errors: %s',
                    $json,
                    $type,
                    implode(', ', $result->issues),
                ),
            );
        } else {
            self::expectIssues($result, $expectedIssues);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('toStringCases')]
    public function testToString(JsonType $type, string $expected): void
    {
        self::assertSame($expected, (string)$type);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('equalsCases')]
    public function testEquals(JsonType $a, JsonType $b, bool $expected): void
    {
        $aString = (string)$a->canonicalize();
        $bString = (string)$b->canonicalize();

        if ($expected) {
            self::assertSame($aString, $bString);
        } else {
            self::assertNotSame($aString, $bString);
        }
    }
}
