<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Circle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Created;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Deleted;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Rectangle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Square;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Triangle;
use RuntimeException;

use function sha1;

/**
 * Unions of several classes that no discriminator identifies, and misplaced #[Discriminator] attributes.
 *
 * @internal
 */
final class DiscriminatedDeclarationErrorCases
{
    private const string TAGS = 'distinct string, int, or backed enum case literal types, such as @var \'circle\'';

    private const string EVENTS = Created::class . ', ' . Deleted::class;

    private const string ATTRIBUTE = '#[\Eventjet\Json\Discriminator(';

    /**
     * @return iterable<string, array{class-string, string}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::inference();
        yield from self::attributes();
        yield from self::misplaced();
    }

    /**
     * @return iterable<string, array{class-string, string}>
     * @throws RuntimeException
     */
    private static function inference(): iterable
    {
        CollectionNameSource::load(
            'DiscriminatedSharedTag',
            'final class DiscriminatedSharedTag { /** @var \'same\' */ public string $type = \'same\';'
            . ' /** @var \'b\'|1 */ public string|int $kind = 1; public int $only = 0;'
            . ' /** @var \'p\'|true */ public string|bool $flag = true;'
            . ' /** @var \'s\' */ public static string $static = \'s\'; }'
            . ' final class DiscriminatedSharedTagOther { /** @var \'same\' */ public string $type = \'same\';'
            . ' /** @var \'a\'|1 */ public string|int $kind = 1; /** @var \'q\' */ public string $flag = \'q\';'
            . ' /** @var \'t\' */ public static string $static = \'t\'; }',
        );
        yield 'classes without a distinct literal member have no discriminator' => self::error(
            'DiscriminatedSharedTagHolder',
            'public DiscriminatedSharedTag|DiscriminatedSharedTagOther $value;',
            'uses multiple class types: DiscriminatedSharedTag, DiscriminatedSharedTagOther. No member identifies the class: every class needs a member that declares '
            . self::TAGS
            . '.',
        );
        yield 'several candidate members need the attribute' => self::error(
            'DiscriminatedSeveral',
            'public \\' . Created::class . '|\\' . Deleted::class . ' $value;',
            'uses multiple class types: '
            . self::EVENTS
            . ', which members @type, schema can each identify. Name one with #[Discriminator].',
        );
        yield 'nullable class union without a discriminator is rejected' => self::error(
            'DiscriminatedNullableShared',
            'public DiscriminatedSharedTag|DiscriminatedSharedTagOther|null $value = null;',
            'uses multiple class types: DiscriminatedSharedTag, DiscriminatedSharedTagOther. No member identifies the class: every class needs a member that declares '
            . self::TAGS
            . '.',
        );
        yield 'an unloaded class leaves the classes ambiguous' => self::error(
            'DiscriminatedUnloaded',
            'public \\' . Circle::class . '|\\' . Rectangle::class . '|DiscriminatedMissingClass $value;',
            'uses multiple class types: DiscriminatedMissingClass, '
            . Circle::class
            . ', '
            . Rectangle::class
            . '. JSON does not identify which class to instantiate.',
        );
        CollectionNameSource::load(
            'DiscriminatedHexTag',
            'final class DiscriminatedHexTag { /** @var 0x1 */ public int $code = 1; }'
            . ' final class DiscriminatedHexTagOther { /** @var 2 */ public int $code = 2; }',
        );
        $hex = CollectionNameSource::load(
            'DiscriminatedHexTagHolder',
            'final class DiscriminatedHexTagHolder { public DiscriminatedHexTag|DiscriminatedHexTagOther $value; }',
        );
        yield 'unsupported number in a member of a class names that class' => [
            $hex,
            'Could not create DiscriminatedHexTag from the JSON object: Field code uses unsupported number literal 0x1, which PHP and PHPStan can read differently. Use a plain decimal number, such as 42, -7, or 1.5.',
        ];
        yield 'class union and map cannot share a union' => self::error(
            'DiscriminatedWithMap',
            '/** @var list<\\'
            . Circle::class
            . '|\\'
            . Rectangle::class
            . '|\ArrayObject<string, int>> */ public array $value;',
            'has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
        );
    }

    /**
     * @return iterable<string, array{class-string, string}>
     * @throws RuntimeException
     */
    private static function attributes(): iterable
    {
        foreach ([
            'a member that is not a literal' => 'id',
            'a misspelled member' => 'tpye',
            'the PHP name of a renamed member' => 'type',
        ] as $label => $member) {
            yield 'discriminator attribute rejects ' . $label => self::error(
                'DiscriminatedInvalid' . sha1($member),
                self::ATTRIBUTE
                . "'"
                . $member
                . "')] public \\"
                . Created::class
                . '|\\'
                . Deleted::class
                . ' $value;',
                'names discriminator '
                . $member
                . ', which does not identify the class among '
                . self::EVENTS
                . ': every class needs the member with '
                . self::TAGS
                . '.',
            );
        }
        yield 'discriminator attribute rejects a member whose values overlap' => self::error(
            'DiscriminatedOverlapping',
            '/** @var list<\\'
            . Square::class
            . '|\\'
            . Triangle::class
            . '> */ '
            . self::ATTRIBUTE
            . "'version')] public array \$value;",
            'names discriminator version, which does not identify the class among '
            . Square::class
            . ', '
            . Triangle::class
            . ': every class needs the member with '
            . self::TAGS
            . '.',
        );
        $class = CollectionNameSource::load(
            'DiscriminatedNullByte',
            'final class DiscriminatedNullByte { '
            . self::ATTRIBUTE
            . '"\0type")] public \\'
            . Circle::class
            . '|\\'
            . Rectangle::class
            . ' $value; }',
        );
        yield 'discriminator attribute rejects a name that starts with a null byte' => [
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: JSON field names must not start with a null byte because PHP treats them as non-public object properties.',
        ];
    }

    /**
     * @return iterable<string, array{class-string, string}>
     * @throws RuntimeException
     */
    private static function misplaced(): iterable
    {
        $circle = '\\' . Circle::class;
        $attribute = self::ATTRIBUTE . "'type')] ";
        foreach ([
            'a single class' => $attribute . 'public ' . $circle . ' $value;',
            'a nullable single class' => $attribute . 'public ?' . $circle . ' $value = null;',
            'a scalar' => $attribute . 'public int $value = 0;',
            'a list of one class' => '/** @var list<' . $circle . '> */ ' . $attribute . 'public array $value;',
            'a class and a scalar' => $attribute . 'public ' . $circle . '|int $value;',
            'a constructor parameter' =>
                'public int $value; public function __construct('
                    . $attribute
                    . 'int $value) { $this->value = $value; }',
        ] as $label => $body) {
            yield 'discriminator attribute is misplaced on ' . $label => self::error(
                'DiscriminatedMisplaced' . sha1($label),
                $body,
                'uses #[Discriminator], but its type has no union of multiple classes.',
            );
        }
    }

    /**
     * @return array{class-string, string}
     * @throws RuntimeException
     */
    private static function error(string $name, string $body, string $error): array
    {
        $class = CollectionNameSource::load($name, 'final class ' . $name . ' { ' . $body . ' }');
        return [$class, 'Could not create ' . $class . ' from the JSON object: Field value ' . $error];
    }
}
