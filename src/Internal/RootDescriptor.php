<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\JsonType;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Throwable;

use function array_reverse;
use function assert;
use function enum_exists;
use function is_array;

/**
 * What a JsonType describes: a JSON array or object at the root, possibly nested in further arrays and objects, around
 * items of one class.
 *
 * @internal
 * @template-covariant T
 */
final readonly class RootDescriptor
{
    /**
     * @template TItem of object
     * @param non-empty-list<'array'|'object'> $shapes The JSON shape of each collection, the root first.
     * @param class-string<TItem> $itemClass
     */
    public function __construct(
        public array $shapes,
        public string $itemClass,
    ) {}

    /**
     * Reads the descriptor of a JsonType. JsonType keeps it private so that users see only its factories, so this reads
     * it with a closure bound to the scope of JsonType. Psalm needs @psalm-scope-this to know that scope. The closure
     * type names object because PHPStan infers its parameter as JsonType without a type argument.
     *
     * @template TValue
     * @param JsonType<TValue> $type
     * @return self<TValue>
     */
    public static function of(JsonType $type): self
    {
        /**
         * @psalm-scope-this JsonType
         * @var Closure(object): RootDescriptor<TValue> $read
         */
        $read = Closure::bind(static fn(JsonType $type): RootDescriptor => $type->descriptor, null, JsonType::class);
        return $read($type);
    }

    /** @return T|DecodeError */
    public function decode(mixed $value): mixed
    {
        /** @var T|DecodeError */
        return $this->decodeRoot($value);
    }

    public function validate(): DecodeError|null
    {
        try {
            $node = $this->node();
            return $node instanceof DecodeError ? $node : new ClassGraphValidator()->validate($this->itemClass);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($this->itemClass, $error);
        }
    }

    /**
     * Returns the node that decodes the root value, or why the item class cannot be decoded.
     *
     * @throws ReflectionException
     */
    private function node(): Node|DecodeError
    {
        $class = $this->itemClass;
        $error = self::validateItem(new ReflectionClass($class));
        if ($error !== null) {
            return $error;
        }
        $enum = $class;
        $node = enum_exists($enum) ? EnumNode::of($class, $enum) : new ObjectNode($class, $class, nullable: false);
        assert($node !== null, description: 'Root validation rejects non-backed enums.');
        foreach (array_reverse($this->shapes) as $shape) {
            $node = $shape === 'array'
                ? new ListNode($class, $node, nonEmpty: false)
                : new MapNode($class, $node, arrayObject: true);
        }
        return $node;
    }

    /**
     * Returns the decoded value without its type. It is a T because the JsonType factories built this descriptor for T,
     * which static analysis cannot follow.
     */
    private function decodeRoot(mixed $value): mixed
    {
        $shape = $this->shapes[0];
        $matches = $shape === 'array' ? is_array($value) : $value instanceof stdClass;
        if (!$matches) {
            return DecodeError::unexpectedRootValue($value, $shape);
        }
        try {
            $node = $this->node();
            return $node instanceof DecodeError ? $node : $node->decode($value, '');
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($this->itemClass, $error);
        }
    }

    /**
     * Unlike a root object, a collection may hold instances of subclasses, so a concrete item class must be final.
     *
     * @template TClass of object
     * @param ReflectionClass<TClass> $class
     */
    private static function validateItem(ReflectionClass $class): DecodeError|null
    {
        $concrete = !$class->isInterface() && !$class->isAbstract();
        return $concrete && !$class->isFinal()
            ? DecodeError::nonInstantiableTarget(
                $class->getName(),
                'Target type is a non-final class. Collection items may be subclasses, whose runtime class JSON does not identify.',
            )
            : RootTypeValidator::validate($class);
    }
}
