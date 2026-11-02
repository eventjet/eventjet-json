<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Circle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Drawing;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\EnumTagged;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\MixedValues;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Rectangle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\ZeroTagged;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;

use function strtolower;

/**
 * JSON values that a discriminated union rejects, with the exact errors.
 *
 * @internal
 */
final class DiscriminatedValueErrorCases
{
    private const string DRAWING = 'Could not create ' . Drawing::class . ' from the JSON object: Field ';

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            'null' => 'null',
            '1.5' => 'float',
            'true' => 'bool',
            '[]' => 'array',
            '{}' => 'stdClass',
        ] as $json => $type) {
            yield 'discriminator of type ' . $type . ' is rejected' => self::drawing(
                '{"type":' . $json . ',"radius":1}',
                'main.type must be one of \'circle\', \'rectangle\', ' . $type . ' given.',
            );
        }
        yield 'discriminator that differs in case is rejected' => self::drawing(
            '{"type":"Circle","radius":1}',
            'main.type must be one of \'circle\', \'rectangle\', \'Circle\' given.',
        );
        $spelled = CollectionNameSource::load(
            'DiscriminatedSpelling',
            'final class DiscriminatedSpelling { public function __construct(public self|discriminatedspelling $next) {} }',
        );
        yield 'a class under two names decodes as that class' => [
            '{"next":null}',
            $spelled,
            'Could not create DiscriminatedSpelling from the JSON object: Field next must be of type DiscriminatedSpelling, null given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'class union rejects a value that is not an object' => self::drawing(
            '"circle"',
            'main must be of type ' . Circle::class . '|' . Rectangle::class . ', string given.',
        );
        yield 'field errors after the class is chosen keep their path' => [
            self::drawingJson('{"type":"rectangle","width":"wide","height":1}'),
            Drawing::class,
            'Could not create '
                . Rectangle::class
                . ' from the JSON object: Field main.width must be of type int, string given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'nullable class union reports null in its type' => self::drawing(
            '{"type":"circle","radius":1},"background":"none"',
            'background must be of type ' . Circle::class . '|' . Rectangle::class . '|null, string given.',
        );
        yield 'discriminator with an integer-like JSON name must be present' => [
            '{"value":{}}',
            ZeroTagged::class,
            'Could not create '
                . ZeroTagged::class
                . ' from the JSON object: Field value has no member 0, which must be one of \'a\', \'b\'.',
            DecodeErrorKind::InvalidValue,
        ];
        $spellings = CollectionDeclarationFixture::create(
            'array',
            'list<\\' . Person::class . '|\\' . strtolower(Person::class) . '>',
            'param',
        );
        yield 'union that names one class in two spellings rejects null' => [
            '{"value":[null]}',
            $spellings,
            'Could not create '
                . $spellings
                . ' from the JSON object: Field value[0] must be of type '
                . Person::class
                . ', null given.',
            DecodeErrorKind::InvalidValue,
        ];
        yield from self::frames();
        yield 'mixed union reports its whole type for an unclaimed JSON type' => [
            '{"value":true,"values":[]}',
            MixedValues::class,
            'Could not create '
                . MixedValues::class
                . ' from the JSON object: Field value must be of type '
                . Circle::class
                . '|'
                . Rectangle::class
                . '|int|string|list<int>|null, bool given.',
            DecodeErrorKind::InvalidValue,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     */
    private static function frames(): iterable
    {
        $prefix =
            'Could not create '
            . EnumTagged::class
            . ' from the JSON object: Field frames[0].op must be one of 1, 2, 8, ';
        foreach (['"1"' => "'1' given.", '1.0' => 'float given.', '15' => '15 given.'] as $op => $error) {
            yield 'integer discriminator rejects ' . $op => [
                '{"marks":[],"frames":[{"op":' . $op . ',"sequence":1}]}',
                EnumTagged::class,
                $prefix . $error,
                DecodeErrorKind::InvalidValue,
            ];
        }
    }

    /** @return array{string, class-string, string, DecodeErrorKind} */
    private static function drawing(string $main, string $error): array
    {
        return [self::drawingJson($main), Drawing::class, self::DRAWING . $error, DecodeErrorKind::InvalidValue];
    }

    private static function drawingJson(string $main): string
    {
        $circle = '{"type":"circle","radius":1}';
        return (
            '{"main":' . $main . ',"shapes":[],"named":{},"layers":{"a":' . $circle . '},"pinned":[' . $circle . ',1]}'
        );
    }
}
