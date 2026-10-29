<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\Internal\Error\DecodeFailure;
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
     * @throws DecodeFailure
     * @throws ReflectionException
     */
    public function validateRoot(string $class): void
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            throw UnsupportedTarget::backedEnum()->whileCreating($class);
        }
        $this->validate($class);
    }

    /**
     * @param class-string $class
     * @throws DecodeFailure
     * @throws ReflectionException
     */
    public function validate(string $class): void
    {
        $class = new ReflectionClass($class)->getName();
        if ($this->visited[$class] ?? false) {
            return;
        }
        $this->visited[$class] = true;
        foreach (ClassDecoders::get($class)->fields as $field) {
            foreach ($field->node->dependencies() as $dependency) {
                if (!class_exists($dependency)) {
                    throw UnsupportedFieldClass::unknown($field->name, $dependency)->whileCreating($class);
                }
                $this->validate($dependency);
            }
        }
    }
}
