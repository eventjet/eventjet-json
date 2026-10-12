<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionException;

use function array_diff;
use function array_intersect;
use function array_map;
use function in_array;

/**
 * Checks that the PHPDoc type of a field narrows its native type: it names the same members, but declares the items of
 * the `array` and `ArrayObject` members, or restricts members to literals. A literal names the type of its value.
 * PHPDoc that adds neither leaves the native type in charge.
 *
 * @internal
 */
final class PhpDocNarrowing
{
    /**
     * Returns the PHPDoc type if it narrows the native type.
     *
     * @param PhpDocType|null $doc A PHPDoc type with resolved names.
     * @throws ReflectionException
     */
    public static function narrowing(PhpDocType|null $doc, PhpDocType $native): PhpDocType|null
    {
        if ($doc === null) {
            return null;
        }
        $names = array_map(static fn(PhpDocType $member): string => $member->name, $native->members());
        $narrows = array_intersect($names, ['array', ArrayObject::class]) !== [];
        $declared = [];
        foreach ($doc->members() as $member) {
            $literal = PhpDocType::literalSyntax($member->name);
            $narrows = $narrows || $literal;
            $declared[] = $literal
                ? PhpDocLiteralNativeType::name($member->name, $names)
                : self::declaredName($member->name);
        }
        // PHPDoc class names have their declared spelling; a native one may differ in case or be an alias.
        $natives = array_map(
            /** @throws ReflectionException */
            static fn(string $name): string => in_array($name, $declared, strict: true)
                ? $name
                : PhpDocClassNameResolver::canonical($name),
            $names,
        );
        $same = array_diff($declared, $natives) === [] && array_diff($natives, $declared) === [];
        return $narrows && $same ? $doc : null;
    }

    private static function declaredName(string $name): string
    {
        return match ($name) {
            'array{}', 'list', 'non-empty-list', 'non-empty-array', 'non-empty-map' => 'array',
            default => $name,
        };
    }
}
