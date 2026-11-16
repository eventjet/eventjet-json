<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationErrorKind;
use Eventjet\Json\Schema\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use stdClass;

use function array_keys;
use function end;
use function in_array;
use function ini_restore;
use function ini_set;
use function is_array;
use function is_string;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * When PCRE gives up on the regexes of a schema, validation never reaches the opposite verdict: it either still reaches
 * the right one or returns a `RegexLimit` error, which names a pattern of the schema and lists no failures. Limits of 0
 * make PCRE give up on most matches. The law leaves out schemas with `format`, whose built-in checks use PCRE too and
 * only promise to stay below the default limits.
 *
 * @internal
 */
final class RegexLimitLaw
{
    private const string PREFIX = 'Could not validate the value: PCRE gave up matching the pattern ';

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws InvalidArgumentException
     */
    public static function assertHolds(
        string $schemaJson,
        mixed $schema,
        Validator $validator,
        mixed $value,
        bool $valid,
    ): void {
        if (!str_contains($schemaJson, '"pattern') || str_contains($schemaJson, '"format"')) {
            return;
        }
        ini_set('pcre.backtrack_limit', value: '0');
        ini_set('pcre.recursion_limit', value: '0');
        $error = $validator->validateValue($value);
        $isValid = $validator->isValid($value);
        ini_restore('pcre.backtrack_limit');
        ini_restore('pcre.recursion_limit');
        if ($error === null || $error->kind !== ValidationErrorKind::RegexLimit) {
            Assert::assertSame($valid, $error === null, (string) $error);
            Assert::assertSame($valid, $isValid);
            return;
        }
        Assert::assertFalse($isValid);
        Assert::assertSame([], $error->failures);
        $message = $error->getMessage();
        Assert::assertMatchesRegularExpression('/^' . self::PREFIX . '.* at \S*: .+ limit exhausted\.$/sD', $message);
        $rest = substr($message, strlen(self::PREFIX));
        $at = (int) strrpos($rest, needle: ' at ');
        $location = substr($rest, $at + 4, (int) strpos($rest, needle: ': ', offset: $at) - $at - 4);
        $fragment = JsonPointer::parseFragment($location);
        Assert::assertContains(end($fragment), ['pattern', 'patternProperties'], $message);
        Assert::assertTrue(
            in_array(substr($rest, offset: 0, length: $at), self::patterns($schema), strict: true),
            $message,
        );
    }

    /**
     * Returns every `pattern` and every member name of `patternProperties` in a JSON document.
     *
     * @return list<string>
     */
    private static function patterns(mixed $document): array
    {
        if (!$document instanceof stdClass && !is_array($document)) {
            return [];
        }
        $patterns = [];
        /** @var mixed $member */
        foreach ((array) $document as $name => $member) {
            if ($name === 'pattern' && is_string($member)) {
                $patterns[] = $member;
            }
            if ($name === 'patternProperties' && $member instanceof stdClass) {
                foreach (array_keys((array) $member) as $pattern) {
                    $patterns[] = (string) $pattern;
                }
            }
            $patterns = [...$patterns, ...self::patterns($member)];
        }
        return $patterns;
    }
}
