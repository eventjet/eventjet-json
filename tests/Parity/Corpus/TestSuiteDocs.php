<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use Eventjet\Json\Test\Acceptance\Cases\ParserSyntaxCases;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

use function array_filter;
use function array_map;
use function array_values;
use function is_string;

/**
 * Collects the doc comments and fields of the classes that the test suite declares (see {@see TestSuiteClasses}), and
 * the types of the parser syntax cases.
 *
 * @internal
 */
final class TestSuiteDocs
{
    private static self|null $collected = null;

    /** @var array<string, true> */
    private array $docs = [];

    /** @var list<ReflectionParameter|ReflectionProperty> */
    private array $fields = [];

    private function __construct() {}

    /**
     * @return iterable<ParityInput>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function inputs(): iterable
    {
        foreach (self::collect()->docs as $doc => $_) {
            yield ParityInput::doc($doc);
        }
        foreach (ParserSyntaxCases::types() as [$source]) {
            yield ParityInput::type($source);
        }
    }

    /**
     * Returns the method parameters and properties that the classes declare.
     *
     * @return list<ReflectionParameter|ReflectionProperty>
     * @throws \ReflectionException
     */
    public static function fields(): array
    {
        return self::collect()->fields;
    }

    /** @throws \ReflectionException */
    private static function collect(): self
    {
        if (self::$collected === null) {
            self::$collected = new self();
            foreach (TestSuiteClasses::load() as $class) {
                self::$collected->add($class);
            }
        }
        return self::$collected;
    }

    /** @param ReflectionClass<object> $class */
    private function add(ReflectionClass $class): void
    {
        $own = static fn(ReflectionMethod|ReflectionProperty $member): bool => $member
            ->getDeclaringClass()
            ->getName() === $class->getName();
        $properties = array_values(array_filter($class->getProperties(), $own));
        $methods = array_values(array_filter($class->getMethods(), $own));
        $docs = [
            $class->getDocComment(),
            ...array_map(
                static fn(ReflectionClassConstant $constant): string|false => $constant->getDocComment(),
                $class->getReflectionConstants(),
            ),
            ...array_map(
                static fn(ReflectionProperty $property): string|false => $property->getDocComment(),
                $properties,
            ),
            ...array_map(static fn(ReflectionMethod $method): string|false => $method->getDocComment(), $methods),
        ];
        foreach (array_filter($docs, is_string(...)) as $doc) {
            $this->docs[$doc] = true;
        }
        $this->fields = [...$this->fields, ...$properties];
        foreach ($methods as $method) {
            $this->fields = [...$this->fields, ...$method->getParameters()];
        }
    }
}
