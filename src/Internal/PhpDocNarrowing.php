<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;

use function array_diff;
use function array_flip;
use function array_intersect_key;
use function in_array;

/**
 * Checks that the PHPDoc type of a field narrows its native type, where either names more than one type: it names the
 * same members, but declares the items of the `array` and `ArrayObject` members, or restricts members to literals. A
 * literal names the type of its value. PHPDoc that adds neither leaves the native type in charge. PHPDoc class names
 * have their declared spelling; a native one may differ in case or be an alias.
 *
 * @internal
 */
final class PhpDocNarrowing
{
    /**
     * Returns the PHPDoc type if it narrows the native type.
     *
     * @param PhpDocType $doc A PHPDoc type with resolved names.
     * @param list<string> $names The member names of the native type.
     * @throws ReflectionException
     */
    public static function narrowing(PhpDocType $doc, array $names): PhpDocType|null
    {
        $narrows = array_intersect_key(array_flip($names), PhpDocType::CONTAINERS) !== [];
        $declared = [];
        foreach ($doc->members() as $member) {
            $literal = $member->literal;
            $narrows = $narrows || $literal !== null;
            $declared[] = $literal === null
                ? PhpDocType::COLLECTIONS[$member->name] ?? $member->name
                : PhpDocLiteralNativeType::name($literal, $names);
        }
        if (!$narrows) {
            return null;
        }
        $natives = [];
        foreach ($names as $name) {
            $natives[] = in_array($name, $declared, strict: true) ? $name : PhpDocClassNameResolver::canonical($name);
        }
        $same = array_diff($declared, $natives) === [] && array_diff($natives, $declared) === [];
        return $same ? $doc : null;
    }
}
