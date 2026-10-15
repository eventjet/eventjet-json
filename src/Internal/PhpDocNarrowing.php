<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionException;

use function array_diff;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function count;
use function in_array;

/**
 * Checks that the PHPDoc type of a field narrows its native type: it names the same members, but declares the items of
 * the `array` and `ArrayObject` members, or restricts members to literals. A literal names the type of its value.
 * PHPDoc that adds neither leaves the native type in charge. PHPDoc class names have their declared spelling; a native
 * one may differ in case or be an alias.
 *
 * @internal
 */
final class PhpDocNarrowing
{
    /** Collection types, which name `array`. */
    private const array COLLECTIONS = [
        'array{}' => 'array',
        'list' => 'array',
        'non-empty-list' => 'array',
        'non-empty-array' => 'array',
        'non-empty-map' => 'array',
    ];

    /** Native types that PHPDoc narrows with item declarations. */
    private const array CONTAINERS = ['array' => true, ArrayObject::class => true];

    /**
     * Returns the PHPDoc type if it narrows the native type.
     *
     * @param PhpDocType|null $doc A PHPDoc type with resolved names.
     * @param list<string> $names The member names of the native type.
     * @throws ReflectionException
     */
    public static function narrowing(PhpDocType|null $doc, array $names): PhpDocType|null
    {
        $narrows =
            $doc !== null
            && match (true) {
                $doc->name === '|', count($names) !== 1 => self::narrowsUnion($doc, $names),
                default => self::narrowsName($doc->name, $names[0], $names),
            };
        return $narrows ? $doc : null;
    }

    /**
     * Whether a PHPDoc type that is no union narrows a native type with one member.
     *
     * @param list<string> $names The member names of the native type.
     * @throws ReflectionException
     */
    private static function narrowsName(string $name, string $native, array $names): bool
    {
        $literal = PhpDocType::literalSyntax($name);
        $declared = $literal ? PhpDocLiteralNativeType::name($name, $names) : self::COLLECTIONS[$name] ?? $name;
        $canonical = match (true) {
            !$literal && !array_key_exists($native, self::CONTAINERS) => null,
            $declared === $native => $native,
            default => PhpDocClassNameResolver::canonical($native),
        };
        return $canonical === $declared;
    }

    /**
     * @param list<string> $names
     * @throws ReflectionException
     */
    private static function narrowsUnion(PhpDocType $doc, array $names): bool
    {
        $narrows = array_intersect_key(array_flip($names), self::CONTAINERS) !== [];
        $declared = [];
        foreach ($doc->members() as $member) {
            $literal = PhpDocType::literalSyntax($member->name);
            $narrows = $narrows || $literal;
            $declared[] = $literal
                ? PhpDocLiteralNativeType::name($member->name, $names)
                : self::COLLECTIONS[$member->name] ?? $member->name;
        }
        if (!$narrows) {
            return false;
        }
        $natives = [];
        foreach ($names as $name) {
            $natives[] = in_array($name, $declared, strict: true) ? $name : PhpDocClassNameResolver::canonical($name);
        }
        return array_diff($declared, $natives) === [] && array_diff($natives, $declared) === [];
    }
}
