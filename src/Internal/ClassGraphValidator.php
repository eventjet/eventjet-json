<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use ReflectionClass;
use ReflectionException;

use function class_exists;

/** @internal */
final class ClassGraphValidator
{
    /** @var array<class-string, true> */
    private array $visited = [];

    /**
     * @param class-string $class
     * @throws DecodeError
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
