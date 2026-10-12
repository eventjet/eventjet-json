<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\JsonType;
use Override;

use function assert;
use function enum_exists;

/**
 * @internal
 * @template-covariant T of object
 * @extends JsonType<T>
 */
final readonly class ClassJsonType extends JsonType
{
    /** @param class-string<T> $class */
    public function __construct(
        private string $class,
    ) {}

    #[Override]
    public function node(string $class): Node
    {
        $enum = $this->class;
        if (enum_exists($enum)) {
            $node = EnumNode::of($class, $enum, nullable: false);
            assert($node !== null, description: 'Root validation rejects non-backed enums.');
            return $node;
        }
        return new ObjectNode($class, $this->class, nullable: false);
    }

    /** @return T|DecodeError */
    #[Override]
    public function decode(mixed $value): object
    {
        /** @var T|DecodeError */
        return $this->node($this->class)->decode($value, '');
    }

    #[Override]
    public function rootShape(): string
    {
        return 'object';
    }

    /** @return class-string<T> */
    #[Override]
    public function itemClass(): string
    {
        return $this->class;
    }
}
