<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonException;

use function array_key_exists;

/**
 * Converts the constructor arguments of a class whose #[Field] names differ from some parameter names. Kept apart from
 * ConstructorPlan, so that classes without such arguments do not load it.
 *
 * @internal
 */
final class RenamedArguments
{
    /**
     * @param array<string, Node|null> $converters Converter of each argument, keyed by parameter name. The converter
     *     is null if the argument needs only its native type check.
     * @param array<string, string> $names JSON names of the arguments whose JSON name differs from their parameter
     *     name, keyed by parameter name.
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    public static function convert(array $converters, array $names, array $values, string $path): array|DecodeError
    {
        $arguments = [];
        foreach ($converters as $parameter => $converter) {
            $name = $names[$parameter] ?? $parameter;
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if ($converter !== null) {
                $value = $converter->decode($value, FieldPath::field($path, $name));
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $arguments[$parameter] = $value;
        }
        return $arguments;
    }
}
