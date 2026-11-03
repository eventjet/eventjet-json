<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembers;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembersList;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectedMembersProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectorNamedLikeField;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;

use function json_encode;
use function sha1;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class OtherMembersErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     */
    public static function documents(): iterable
    {
        yield 'encoding rejects other members that repeat a declared JSON name' => [
            '{"$id":"a","count":null}',
            CollectedMembers::class,
            /** @throws InvalidArgumentException */
            static function (object $decoded): bool {
                $value = new JsonValue(1);
                $property = new CollectedMembersProperty();
                $property->others = ['name' => $value];
                $rejected = [
                    'written field' => [new CollectedMembers('a', others: ['$id' => $value]), '$id'],
                    'field written as null' => [new CollectedMembers('a', others: ['count' => $value]), 'count'],
                    'absent JsonValue|null field' => [new CollectedMembers('a', others: ['extra' => $value]), 'extra'],
                    'uninitialized field' => [$property, 'name'],
                    'renamed field' => [new CollectorNamedLikeField('l', ['others' => $value]), 'others'],
                ];
                $property = new CollectedMembersProperty();
                $property->name = 'n';
                $encoded = [
                    [$decoded, '{"$id":"a","count":null}'],
                    [
                        new CollectedMembers('a', others: ['id' => $value, 'x' => $value]),
                        '{"$id":"a","count":null,"id":1,"x":1}',
                    ],
                    [$property, '{"name":"n"}'],
                ];
                foreach ($rejected as [$object, $name]) {
                    $encoded[] = [$object, self::repeated($object, $name)];
                }
                foreach ($encoded as [$object, $expected]) {
                    $actual = self::encoded($object);
                    if ($actual !== $expected) {
                        return false;
                    }
                }
                return true;
            },
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::declarations();
        yield 'number too large for a float in an other member' => [
            '{"$id":"a","x":1e400}',
            CollectedMembers::class,
            sprintf(
                'Could not create %s from the JSON object: Field x is not a valid JSON value. JsonValue cannot hold the float INF, which has no JSON representation.',
                CollectedMembers::class,
            ),
            DecodeErrorKind::InvalidValue,
        ];
        yield 'number too large for a float in a nested other member' => [
            '{"items":[{"$id":"a"},{"$id":"b","0":[-1e400]}]}',
            CollectedMembersList::class,
            sprintf(
                'Could not create %s from the JSON object: Field items[1].0 is not a valid JSON value. JsonValue cannot hold the float -INF, which has no JSON representation.',
                CollectedMembers::class,
            ),
            DecodeErrorKind::InvalidValue,
        ];
    }

    /**
     * Declarations that cannot decode, whatever the input.
     *
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    public static function declarations(): iterable
    {
        yield from self::attributeErrors();
        yield from self::typeErrors();
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    private static function attributeErrors(): iterable
    {
        $serializable = self::serializable();
        $collecting = '/** @var array<array-key, \\' . JsonValue::class . '> */ ' . self::attribute();
        $class = self::declaring(
            'OtherMembersWithoutSerializer',
            ' { ' . $collecting . ' public array $others = []; }',
        );
        yield '#[OtherMembers] without JsonSerializable' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: #[OtherMembers] requires the target class to implement JsonSerializable, because json_encode() would write the collected members as one nested member.',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $class = self::declaring(
            'OtherMembersTwice',
            $serializable
            . ' '
            . $collecting
            . ' public array $first = []; '
            . $collecting
            . ' public array $second = []; }',
        );
        yield '#[OtherMembers] on two properties' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: Properties first and second both declare #[OtherMembers], but only one property can collect the other members.',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        foreach ([
            'private' => 'private array $others = [];',
            'protected' => 'protected array $others = [];',
            'static' => 'public static array $others = [];',
        ] as $label => $declaration) {
            $class = self::declaring(
                'OtherMembers_' . sha1($declaration),
                $serializable . ' ' . $collecting . ' ' . $declaration . ' }',
            );
            yield '#[OtherMembers] on a ' . $label . ' property' => [
                '{}',
                $class,
                sprintf(
                    'Could not create %s from the JSON object: #[OtherMembers] requires a public instance property; others is not one.',
                    $class,
                ),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        $base = CollectionNameSource::load(
            'OtherMembersPrivateBase',
            'abstract class OtherMembersPrivateBase { ' . $collecting . ' private array $others = []; }',
        );
        $class = self::declaring('OtherMembersPrivateAncestor', ' extends ' . $base . $serializable . ' }');
        yield '#[OtherMembers] on a private property of an ancestor' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: #[OtherMembers] requires a public instance property; others is not one.',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $class = self::declaring(
            'OtherMembersBeforeCollision',
            $serializable
            . ' '
            . $collecting
            . ' public array $others = []; #[\\'
            . Field::class
            . '("a")] public int $x = 0; public int $a = 0; }',
        );
        yield 'colliding JSON names after #[OtherMembers]' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: Properties x and a use the same JSON field name "a".',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $class = self::declaring(
            'OtherMembersWithField',
            $serializable . ' ' . $collecting . ' #[\\' . Field::class . '("rest")] public array $others = []; }',
        );
        yield '#[OtherMembers] with #[Field]' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: Property others declares both #[Field] and #[OtherMembers]. The property that collects the other members has no JSON name of its own.',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    private static function typeErrors(): iterable
    {
        $attribute = self::attribute();
        $value = '\\' . JsonValue::class;
        foreach ([
            'nullable array' =>
                '/** @var array<array-key, ' . $value . '>|null */ ' . $attribute . ' public ?array $others = null;',
            'union with array' =>
                '/** @var array<array-key, '
                    . $value
                    . '>|string */ '
                    . $attribute
                    . ' public array|string $others = [];',
            'array without a PHPDoc type' => $attribute . ' public array $others = [];',
            'array of string keys' =>
                '/** @var array<string, ' . $value . '> */ ' . $attribute . ' public array $others = [];',
            'array of int keys' =>
                '/** @var array<int, ' . $value . '> */ ' . $attribute . ' public array $others = [];',
            'array of nullable values' =>
                '/** @var array<array-key, ' . $value . '|null> */ ' . $attribute . ' public array $others = [];',
            'array of other values' =>
                '/** @var array<array-key, int> */ ' . $attribute . ' public array $others = [];',
            'array of unresolvable values' =>
                '/** @var array<array-key, OtherMembersMissing> */ ' . $attribute . ' public array $others = [];',
            'list' => '/** @var list<' . $value . '> */ ' . $attribute . ' public array $others = [];',
            'non-empty array' =>
                '/** @var non-empty-array<array-key, ' . $value . '> */ ' . $attribute . ' public array $others = [];',
            'array shape' => '/** @var array{a: ' . $value . '} */ ' . $attribute . ' public array $others = [];',
            'ArrayObject' =>
                '/** @var \\ArrayObject<array-key, ' . $value . '> */ ' . $attribute . ' public \\ArrayObject $others;',
            'variadic constructor parameter' =>
                $attribute
                    . ' public array $others; /** @param array<array-key, '
                    . $value
                    . '> ...$others */ public function __construct(array ...$others) {}',
            'constructor parameter of another type' =>
                $attribute
                    . ' public array $others; /** @param array<array-key, '
                    . $value
                    . '> $others */ public function __construct(string $others) {}',
        ] as $label => $declaration) {
            $class = self::declaring(
                'OtherMembersType_' . sha1($declaration),
                self::serializable() . ' ' . $declaration . ' }',
            );
            yield 'other members declared as ' . $label => [
                '{}',
                $class,
                sprintf(
                    'Could not create %s from the JSON object: Field others collects the other members, so it must be declared as array<array-key, %s>.',
                    $class,
                    JsonValue::class,
                ),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
    }

    private static function attribute(): string
    {
        return '#[\\' . OtherMembers::class . ']';
    }

    private static function serializable(): string
    {
        return ' implements \\' . JsonSerializable::class . ' { use \\' . MappedJsonFields::class . ';';
    }

    /**
     * @return class-string
     * @throws RuntimeException
     */
    private static function declaring(string $class, string $body): string
    {
        return CollectionNameSource::load($class, 'final class ' . $class . $body);
    }

    /** Returns the JSON for an object, or the message of the exception that encoding it throws. */
    private static function encoded(object $object): string
    {
        try {
            return json_encode($object, JSON_THROW_ON_ERROR);
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }

    private static function repeated(object $object, string $name): string
    {
        return sprintf(
            'Cannot encode %s, because its property others holds a member named "%s", which is the JSON name of a declared field.',
            $object::class,
            $name,
        );
    }
}
