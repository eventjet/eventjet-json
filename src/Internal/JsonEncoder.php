<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\JsonError;
use JsonException;
use ReflectionObject;
use ReflectionProperty;

use function array_is_list;
use function array_key_exists;
use function array_pop;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class JsonEncoder
{
    /** @var list<object> */
    private array $ancestors = [];

    public static function encode(mixed $value, PhpType|null $type = null): string
    {
        return (new self())->value($value, type: $type);
    }

    private function value(mixed $value, JsonNode|null $source = null, PhpType|null $type = null, int $depth = 0): string
    {
        if ($depth > 512) {
            throw JsonError::encodeFailed('Maximum JSON depth exceeded');
        }
        if ($value instanceof BackedEnum) {
            return $this->value($value->value, $source, depth: $depth);
        }
        if (is_object($value)) {
            if (in_array($value, $this->ancestors, true)) {
                throw JsonError::encodeFailed('Recursive object graph');
            }
            $this->ancestors[] = $value;
            try {
                return $this->object($value, $source, $depth);
            } finally {
                array_pop($this->ancestors);
            }
        }
        if (is_array($value)) {
            return $this->array($value, $source, $type, $depth);
        }
        if ($source !== null && $source->number !== null && $source->value === $value) {
            return $source->number;
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw JsonError::encodeFailed($exception->getMessage(), $exception);
        }
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function array(array $values, JsonNode|null $source, PhpType|null $type, int $depth): string
    {
        $source = $source !== null && is_array($source->value) ? $source : null;
        $object = !array_is_list($values) || ($source->object ?? false);
        $collection = CollectionType::forEncoding($type, $object, $values, $source);
        if ($source?->object === true && $collection === null) {
            $source = null;
        }
        $object = !array_is_list($values) || ($collection->object ?? $source->object ?? false);
        $sourceMembers = $source->value ?? [];
        $members = [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            $members[] = ($object ? $this->value((string)$key) . ':' : '') . $this->value($value, $sourceMembers[$key] ?? null, $collection?->item, $depth + 1);
        }
        return ($object ? '{' : '[') . implode(',', $members) . ($object ? '}' : ']');
    }

    private function object(object $object, JsonNode|null $source, int $depth): string
    {
        $state = DecodedObject::registry()[$object] ?? null;
        $sourceMembers = $state->members ?? ($source !== null && is_array($source->value) ? $source->value : []);
        $members = [];
        foreach ((new ReflectionObject($object))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || !$property->isInitialized($object)) {
                continue;
            }
            $name = $property->getName();
            /** @var mixed $value */
            $value = $property->getValue($object);
            $key = JsonDecoder::key($property);
            if (($state->omittedDefaults[$name] ?? null)?->matches($value) === true) {
                continue;
            }
            $members[$key] = $this->value($value, $sourceMembers[$key] ?? null, is_array($value) ? PhpType::property($property) : null, $depth + 1);
        }
        if ($state !== null) {
            foreach ($state->unknown as $key => $node) {
                if (array_key_exists($key, $members)) {
                    continue;
                }
                $members[$key] = $this->value($node->native(), $node, depth: $depth + 1);
            }
        }
        $parts = [];
        foreach ($members as $key => $encoded) {
            $parts[] = $this->value((string)$key) . ':' . $encoded;
        }
        return '{' . implode(',', $parts) . '}';
    }
}
