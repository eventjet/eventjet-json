<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use Eventjet\Json\Test\Parity\Corpus\Dice;

use function assert;

/**
 * A generated object graph of one fixture set, with every object that a discriminated union in it holds.
 *
 * @internal
 */
final class Document
{
    public readonly object $root;

    /** @var list<TaggedObject> */
    private array $tagged = [];

    public function __construct(
        string $set,
        public readonly Dice $dice,
    ) {
        $this->root = match ($set) {
            'flat' => ShapeDocuments::drawing($this),
            'mixed' => ShapeDocuments::mixed($this),
            'enum tags' => TagDocuments::enumTagged($this),
            'aliases' => TagDocuments::aliases($this),
            'named' => EventDocuments::eventLog($this),
            default => FilterDocuments::query($this),
        };
    }

    /** Returns the path of a member of the object at the given path, as decode errors show it. */
    public static function memberPath(string $path, string $member): string
    {
        return $path === '' ? $member : $path . '.' . $member;
    }

    /**
     * Records an object that a discriminated union holds.
     *
     * @template T of object
     * @param string $container The class whose field holds the union.
     * @param T $object
     * @return T
     */
    public function tagged(string $container, string $path, object $object, Tag $tag): object
    {
        $this->tagged[] = new TaggedObject($container, $path, $object, $tag);
        return $object;
    }

    public function pickTagged(): TaggedObject
    {
        $tagged = $this->tagged;
        assert($tagged !== [], description: 'Every generated document holds a discriminated object.');
        return $this->dice->pick($tagged);
    }
}
