<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Field;
use JsonSerializable;
use LogicException;
use ReflectionClass;
use stdClass;

final class FieldMapping
{
    private static array $names = [];
    private static array $annotated = [];

    public static function resolve(ReflectionClass $class): array
    {
        $name = $class->getName();
        if (isset(self::$names[$name])) {
            return self::$names[$name];
        }
        $names = [];
        $owners = [];
        $annotated = false;
        foreach ($class->getProperties() as $property) {
            $attributes = $property->getAttributes(Field::class);
            if (!$property->isPublic() || $property->isStatic()) {
                if ($attributes !== []) {
                    throw new LogicException('#[Field] requires a public instance property: ' . $property->getName());
                }
                continue;
            }
            $annotated = $annotated || $attributes !== [];
            $wire = $attributes === [] ? $property->getName() : $attributes[0]->newInstance()->name;
            if (isset($owners[$wire])) {
                throw new LogicException('Duplicate JSON field name: ' . $wire);
            }
            $owners[$wire] = $property->getName();
            $names[$property->getName()] = $wire;
        }
        if ($annotated && !$class->implementsInterface(JsonSerializable::class)) {
            throw new LogicException('#[Field] requires JsonSerializable: ' . $name);
        }
        self::$annotated[$name] = $annotated;
        return self::$names[$name] = $names;
    }

    public static function annotated(ReflectionClass $class): bool
    {
        self::resolve($class);
        return self::$annotated[$class->getName()];
    }

    public static function serialize(object $object): stdClass
    {
        $names = self::$names[$object::class] ?? self::resolve(new ReflectionClass($object));
        $result = new stdClass();
        foreach (get_object_vars($object) as $name => $value) {
            $result->{$names[$name] ?? $name} = $value;
        }
        return $result;
    }
}
