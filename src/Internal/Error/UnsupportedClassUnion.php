<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;

use function implode;
use function sort;
use function sprintf;

/**
 * Errors for unions of several classes whose objects no member identifies, and for misplaced #[Discriminator]
 * attributes.
 *
 * @internal
 */
final class UnsupportedClassUnion
{
    private const string TAGS = 'distinct string, int, or backed enum case literal types, such as @var \'circle\'';

    /** @param list<string> $classes */
    public static function withoutDiscriminator(string $field, array $classes): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses multiple class types: %s. No member identifies the class: every class needs a member that declares %s.',
            $field,
            self::names($classes),
            self::TAGS,
        ));
    }

    /**
     * @param list<string> $classes
     * @param list<string> $members
     */
    public static function severalDiscriminators(string $field, array $classes, array $members): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses multiple class types: %s, which members %s can each identify. Name one with #[Discriminator].',
            $field,
            self::names($classes),
            implode(', ', $members),
        ));
    }

    /** @param list<string> $classes */
    public static function invalidDiscriminator(string $field, string $member, array $classes): DecodeError
    {
        return self::error(sprintf(
            'Field %s names discriminator %s, which does not identify the class among %s: every class needs the member with %s.',
            $field,
            $member,
            self::names($classes),
            self::TAGS,
        ));
    }

    public static function misplacedDiscriminator(string $field): DecodeError
    {
        return self::error(sprintf(
            'Field %s uses #[Discriminator], but its type has no union of multiple classes.',
            $field,
        ));
    }

    /** @param list<string> $classes */
    private static function names(array $classes): string
    {
        sort($classes);
        return implode(', ', $classes);
    }

    private static function error(string $message): DecodeError
    {
        return new DecodeError(DecodeErrorKind::UnsupportedDeclaration, $message);
    }
}
