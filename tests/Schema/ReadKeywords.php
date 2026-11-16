<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;

use function in_array;
use function is_array;

/**
 * The keywords that the validator reads in each draft, other than `const`, which takes any value.
 *
 * @internal
 */
final class ReadKeywords
{
    private const array ALL_DRAFTS = [
        'type',
        'enum',
        'multipleOf',
        'maximum',
        'minimum',
        'exclusiveMaximum',
        'exclusiveMinimum',
        'maxLength',
        'minLength',
        'pattern',
        'items',
        'maxItems',
        'minItems',
        'uniqueItems',
        'maxProperties',
        'minProperties',
        'required',
        'properties',
        'patternProperties',
        'additionalProperties',
        'allOf',
        'anyOf',
        'oneOf',
        'not',
    ];

    private const array SINCE_DRAFT6 = ['contains', 'propertyNames'];

    private const array SINCE_DRAFT201909 = [
        'dependentRequired',
        'dependentSchemas',
        'unevaluatedItems',
        'unevaluatedProperties',
    ];

    /**
     * Returns whether the validator reads the keyword of a schema with these members. `additionalItems` counts only next
     * to an array of `items`, because otherwise it has no effect.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @param array<array-key, mixed> $members
     * @pure
     */
    public static function has(string $class, int|string $keyword, array $members): bool
    {
        if ($keyword === 'additionalItems') {
            return $class !== Draft202012::class && is_array($members['items'] ?? null);
        }
        $keywords = match ($class) {
            Draft4::class => ['dependencies'],
            Draft6::class => [...self::SINCE_DRAFT6, 'dependencies'],
            Draft7::class => [...self::SINCE_DRAFT6, 'dependencies', 'if'],
            Draft201909::class => [...self::SINCE_DRAFT6, 'if', ...self::SINCE_DRAFT201909],
            default => [...self::SINCE_DRAFT6, 'if', ...self::SINCE_DRAFT201909, 'prefixItems'],
        };
        return in_array($keyword, [...self::ALL_DRAFTS, ...$keywords], strict: true);
    }
}
