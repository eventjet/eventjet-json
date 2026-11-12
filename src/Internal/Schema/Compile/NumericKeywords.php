<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\MultipleOfKeyword;
use Eventjet\Json\Internal\Schema\Keyword\NumberLimitKeyword;
use Eventjet\Json\Schema\SchemaError;

use function is_float;
use function is_int;
use function sprintf;

/**
 * Compiles `multipleOf` and the limits of numbers.
 *
 * @internal
 */
final class NumericKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(SchemaObject $schema): array
    {
        $keywords = [];
        if ($schema->has('multipleOf')) {
            /** @var mixed $divisor */
            $divisor = $schema->get('multipleOf');
            if (!is_int($divisor) && !is_float($divisor) || $divisor <= 0) {
                throw new SchemaError(sprintf(
                    'The keyword at %s must be a number greater than 0.',
                    $schema->at('multipleOf'),
                ));
            }
            $keywords[] = new MultipleOfKeyword($divisor, $schema->at('multipleOf'));
        }
        return [
            ...$keywords,
            ...self::limits($schema, name: 'maximum', exclusiveName: 'exclusiveMaximum', isMaximum: true),
            ...self::limits($schema, name: 'minimum', exclusiveName: 'exclusiveMinimum', isMaximum: false),
        ];
    }

    /**
     * Compiles a limit and its exclusive form, which is a separate keyword since Draft 6 and a Boolean before.
     *
     * @return list<NumberLimitKeyword>
     * @throws SchemaError
     */
    private static function limits(SchemaObject $schema, string $name, string $exclusiveName, bool $isMaximum): array
    {
        $limit = self::number($schema, $name);
        if ($schema->draft === Draft::Draft4) {
            $exclusive = KeywordValues::boolean($schema, $exclusiveName) === true;
            return (
                $limit === null
                    ? []
                    : [new NumberLimitKeyword($name, $limit, $isMaximum, $exclusive, $schema->at($name))]
            );
        }
        $keywords = [];
        if ($limit !== null) {
            $keywords[] = new NumberLimitKeyword(
                $name,
                $limit,
                $isMaximum,
                exclusive: false,
                location: $schema->at($name),
            );
        }
        $exclusiveLimit = self::number($schema, $exclusiveName);
        if ($exclusiveLimit !== null) {
            $keywords[] = new NumberLimitKeyword(
                $exclusiveName,
                $exclusiveLimit,
                $isMaximum,
                exclusive: true,
                location: $schema->at($exclusiveName),
            );
        }
        return $keywords;
    }

    /**
     * Returns the number that a keyword holds, or null if it is missing.
     *
     * @throws SchemaError
     */
    private static function number(SchemaObject $schema, string $keyword): int|float|null
    {
        if (!$schema->has($keyword)) {
            return null;
        }
        /** @var mixed $value */
        $value = $schema->get($keyword);
        if (!is_int($value) && !is_float($value)) {
            throw new SchemaError(sprintf('The keyword at %s must be a number.', $schema->at($keyword)));
        }
        return $value;
    }
}
