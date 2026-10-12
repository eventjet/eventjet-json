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
        $reflection = new ReflectionClass($class);
        $class = $reflection->getName();
        if ($this->visited[$class] ?? false) {
            return null;
        }
        $this->visited[$class] = true;
        $error = RootTypeValidator::validate($reflection);
        if ($error !== null) {
            return $error;
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $compiler = new TypeCompiler($class);
            $node = new ConstructorParameter($parameter, $reflection)->resolveType($compiler);
            $error = $node instanceof DecodeError ? $node : $this->dependencies($class, $parameter->getName(), [
                    ...$compiler->unresolved,
                    ...$node->dependencies(),
                ]);
            if ($error !== null) {
                return $error;
            }
        }

        $properties = PublicProperties::resolve($reflection);
        if ($properties instanceof DecodeError) {
            return $properties;
        }
        foreach ($properties as $property) {
            $error = $this->dependencies($class, $property['property']->getName(), $property['node']->dependencies());
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * @param class-string $class
     * @param list<string> $dependencies
     * @throws ReflectionException
     */
    private function dependencies(string $class, string $field, array $dependencies): DecodeError|null
    {
        foreach ($dependencies as $dependency) {
            $error = class_exists($dependency)
                ? $this->validate($dependency)
                : DecodeError::nonInstantiableField($class, $field, 'unknown class', $dependency);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }
}
