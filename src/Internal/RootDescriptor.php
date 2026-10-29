<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
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
use function enum_exists;
use function implode;
use function is_array;

/**
 * What a JsonType describes: a JSON array or object at the root, possibly nested in further arrays and objects, around
 * items of one class. Equivalent descriptors share one node. Descriptors that fail validation are compiled again on the
 * next use.
 *
 * @internal
 * @template-covariant T
 */
final class RootDescriptor
{
    /** @var array<string, Node> */
    private static array $nodes = [];

    /**
     * @template TItem of object
     * @param non-empty-list<'array'|'object'> $shapes The JSON shape of each collection, the root first.
     * @param class-string<TItem> $itemClass
     */
    public function __construct(
        public readonly array $shapes,
        public readonly string $itemClass,
    ) {}

    /**
     * Reads the descriptor of a JsonType. JsonType keeps it private so that users see only its factories, so this reads
     * it with a closure bound to the scope of JsonType. A ReflectionProperty would make Json::decode() declare a
     * ReflectionException that cannot happen. Psalm needs @psalm-scope-this to know that scope. The closure
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
            $this->node();
            $enum = $this->itemClass;
            if (!enum_exists($enum)) {
                new ClassGraphValidator()->validate($this->itemClass);
            }
            return null;
        } catch (DecodeError $error) {
            return $error;
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($this->itemClass);
        }
    }

    /**
     * Returns the node that decodes the root value. Unlike a class decoder, a compiled node never needs to be compiled
     * again: it refers only to the item class, which validation loads.
     *
     * @throws DecodeError If the item class cannot be decoded.
     * @throws ReflectionException
     */
    public function node(): Node
    {
        $key = implode(' ', [...$this->shapes, $this->itemClass]);
        $node = self::$nodes[$key] ?? null;
        if ($node !== null) {
            return $node;
        }
        $class = $this->itemClass;
        try {
            TargetClass::validateNested(new ReflectionClass($class));
        } catch (DecodeError $error) {
            throw $error->whileCreating($class);
        }
        $enum = $class;
        $node = enum_exists($enum) ? EnumNode::of($enum) : new ObjectNode($class, nullable: false);
        foreach (array_reverse($this->shapes) as $shape) {
            $node = $shape === 'array' ? new ListNode($node, nonEmpty: false) : new MapNode($node, arrayObject: true);
        }
        return self::$nodes[$key] = $node;
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
            return InvalidInput::rootValue($value, $shape);
        }
        try {
            return $this->node()->decode($value, '');
        } catch (DecodeError $error) {
            return $error->whileCreating($this->itemClass);
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($this->itemClass);
        }
    }
}
