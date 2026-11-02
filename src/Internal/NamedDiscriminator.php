<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Discriminator;
use Eventjet\Json\Internal\Error\UnsupportedClassUnion;
use Eventjet\Json\Internal\Node\Node;
use ReflectionAttribute;

/**
 * The member that #[Discriminator] names for the unions of several classes in one field declaration. It records whether
 * the declaration has such a union, because an attribute without one is a mistake.
 *
 * @internal
 */
final class NamedDiscriminator
{
    public bool $applied = false;

    private function __construct(
        public readonly string $member,
    ) {}

    /** @param ReflectionAttribute<Discriminator> $attribute */
    public static function of(ReflectionAttribute $attribute): self
    {
        return new self($attribute->newInstance()->name);
    }

    /**
     * Returns the compiled node of the field, or the error if it has no union of several classes.
     *
     * @throws DecodeError
     */
    public function check(Node $node, string $field): Node
    {
        return $this->applied ? $node : throw UnsupportedClassUnion::misplacedDiscriminator($field);
    }
}
