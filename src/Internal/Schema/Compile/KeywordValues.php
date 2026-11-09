<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\EcmaRegex;
use Eventjet\Json\Internal\Schema\JsonKey;
use Eventjet\Json\Schema\SchemaError;

use function array_is_list;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Reads the values of keywords that aren't schemas, and fails for values of the wrong type.
 *
 * @internal
 */
final class KeywordValues
{
    /**
     * @return list<string>
     * @throws SchemaError
     */
    public static function strings(mixed $value, string $location): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaError(sprintf('The keyword at %s must be an array of strings.', $location));
        }
        $strings = [];
        /** @var mixed $item */
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new SchemaError(sprintf('The keyword at %s must be an array of strings.', $location));
            }
            $strings[] = $item;
        }
        return $strings;
    }

    /**
     * Returns the non-negative integer that a keyword holds, or null if it is missing. Draft 4 doesn't allow floats.
     *
     * @throws SchemaError
     */
    public static function count(SchemaObject $schema, string $keyword): int|float|null
    {
        if (!$schema->has($keyword)) {
            return null;
        }
        /** @var mixed $value */
        $value = $schema->get($keyword);
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if ($schema->draft !== Draft::Draft4 && is_float($value) && JsonKey::isIntegral($value) && $value >= 0) {
            return $value;
        }
        throw new SchemaError(sprintf('The keyword at %s must be a non-negative integer.', $schema->at($keyword)));
    }

    /**
     * Returns the Boolean that a keyword holds, or null if it is missing.
     *
     * @throws SchemaError
     */
    public static function boolean(SchemaObject $schema, string $keyword): bool|null
    {
        if (!$schema->has($keyword)) {
            return null;
        }
        /** @var mixed $value */
        $value = $schema->get($keyword);
        if (is_bool($value)) {
            return $value;
        }
        throw new SchemaError(sprintf('The keyword at %s must be a Boolean.', $schema->at($keyword)));
    }

    /**
     * @return non-empty-string
     * @throws SchemaError
     */
    public static function regex(string $pattern, string $location): string
    {
        $pcre = EcmaRegex::toPcre($pattern);
        if ($pcre === null) {
            throw new SchemaError(sprintf('The regex at %s is not valid ECMA-262: %s', $location, $pattern));
        }
        set_error_handler(static fn(): bool => true);
        $runs = preg_match($pcre, subject: '') !== false;
        restore_error_handler();
        if (!$runs) {
            throw new SchemaError(sprintf(
                'The regex at %s is valid ECMA-262, but PCRE cannot run it: %s',
                $location,
                $pattern,
            ));
        }
        return $pcre;
    }
}
