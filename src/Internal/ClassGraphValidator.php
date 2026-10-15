<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function is_subclass_of;

/** @internal */
final class ClassGraphValidator
{
    /** @var array<class-string, true> */
    private array $visited = [];

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public function validateRoot(string $class): DecodeError|null
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            return UnsupportedTarget::backedEnum()->whileCreating($class);
        }
        return $this->validate($class);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public function validate(string $class): DecodeError|null
    {
        $class = new ReflectionClass($class)->getName();
        if ($this->visited[$class] ?? false) {
            return null;
        }
        $this->visited[$class] = true;
        $decoder = ClassDecoders::get($class);
        if ($decoder instanceof DecodeError) {
            return $decoder;
        }
        foreach ($decoder->fields() as $field => $node) {
            foreach ($node->dependencies() as $dependency) {
                $error = class_exists($dependency)
                    ? $this->validate($dependency)
                    : UnsupportedFieldClass::unknown($field, $dependency)->whileCreating($class);
                if ($error !== null) {
                    return $error;
                }
            }
        }
        return null;
    }
}
