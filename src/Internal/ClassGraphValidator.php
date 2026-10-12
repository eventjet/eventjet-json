<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
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
            return DecodeError::nonInstantiableTarget(
                $class,
                'Backed enums are not supported as standalone targets. Use a typed field or a collection descriptor.',
            );
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
                    : DecodeError::nonInstantiableField($class, $field, 'unknown class', $dependency);
                if ($error !== null) {
                    return $error;
                }
            }
        }
        return null;
    }
}
