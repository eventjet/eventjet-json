<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\Error\InvalidDiscriminator;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldPath;
use Override;
use stdClass;

use function array_key_exists;
use function get_object_vars;
use function implode;
use function is_int;
use function is_string;
use function var_export;

/**
 * Decodes a JSON object into the class that the value of its discriminator member selects, from a union of several
 * classes. The chosen class decodes every member, including the discriminator.
 *
 * @internal
 */
final readonly class DiscriminatedNode implements Node
{
    private string $type;

    /**
     * @param non-empty-list<class-string> $classes
     * @param array<string, class-string> $strings The class of each string value of the discriminator.
     * @param array<int, class-string> $ints The class of each integer value of the discriminator.
     * @param string $allowed The values of the discriminator, as errors list them.
     */
    private function __construct(
        private array $classes,
        private string $member,
        private array $strings,
        private array $ints,
        private string $allowed,
    ) {
        $this->type = implode('|', $classes);
    }

    /**
     * @param non-empty-list<class-string> $classes
     * @param non-empty-list<array{int|string, class-string}> $tags Each value of the discriminator with its class.
     */
    public static function of(array $classes, string $member, array $tags): self
    {
        $strings = [];
        $ints = [];
        $listed = [];
        foreach ($tags as [$value, $class]) {
            $listed[] = var_export($value, return: true);
            if (is_int($value)) {
                $ints[$value] = $class;
                continue;
            }
            $strings[$value] = $class;
        }
        return new self($classes, $member, $strings, $ints, implode(', ', $listed));
    }

    #[Override]
    public function decode(mixed $value, string $path): object
    {
        if (!$value instanceof stdClass) {
            throw InvalidValue::typeMismatch($path, $this->type, $value);
        }
        $members = get_object_vars($value);
        /** @var mixed $tag */
        $tag = $members[$this->member] ?? null;
        $class = match (true) {
            is_string($tag) => $this->strings[$tag] ?? null,
            is_int($tag) => $this->ints[$tag] ?? null,
            default => null,
        };
        return $class === null
            ? throw $this->invalid($members, $tag, $path)
            : ClassDecoders::hydrate($class, $value, $path);
    }

    /** @param array<array-key, mixed> $members */
    private function invalid(array $members, mixed $tag, string $path): DecodeError
    {
        $member = FieldPath::field($path, $this->member);
        return match (true) {
            !array_key_exists($this->member, $members) => InvalidDiscriminator::missing(
                $path,
                $this->member,
                $this->allowed,
            ),
            is_string($tag), is_int($tag) => InvalidDiscriminator::unknown($member, $this->allowed, $tag),
            default => InvalidDiscriminator::type($member, $this->allowed, $tag),
        };
    }

    #[Override]
    public function claims(): array
    {
        return ['stdClass' => ['others' => 'accepts', 'enums' => [], 'values' => []]];
    }

    #[Override]
    public function dependencies(): array
    {
        return $this->classes;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
