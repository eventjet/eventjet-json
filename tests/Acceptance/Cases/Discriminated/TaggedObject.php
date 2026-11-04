<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use BackedEnum;
use JsonException;
use ReflectionException;
use ReflectionProperty;

use function json_encode;
use function str_replace;

use const JSON_THROW_ON_ERROR;

/**
 * An object in a generated document that a discriminated union holds.
 *
 * @internal
 */
final readonly class TaggedObject
{
    /** @param string $container The class whose field holds the union. */
    public function __construct(
        public string $container,
        public string $path,
        private object $object,
        public Tag $tag,
    ) {}

    /**
     * Gives the object the discriminator value that no class declares. No other object in the document has it.
     *
     * @throws ReflectionException
     */
    public function replaceTag(): void
    {
        new ReflectionProperty($this->object, $this->tag->property)->setValue($this->object, $this->tag->undeclared);
    }

    /**
     * Removes the discriminator of the object from the JSON document after {@see self::replaceTag()}.
     *
     * @throws JsonException
     */
    public function withoutTag(string $json): string
    {
        $member =
            json_encode($this->tag->member, JSON_THROW_ON_ERROR)
            . ':'
            . json_encode($this->undeclared(), JSON_THROW_ON_ERROR);
        return str_replace([',' . $member, $member . ',', $member], replace: '', subject: $json);
    }

    /** Returns the JSON value of the discriminator value that no class declares. */
    public function undeclared(): string|int
    {
        $undeclared = $this->tag->undeclared;
        return $undeclared instanceof BackedEnum ? $undeclared->value : $undeclared;
    }
}
