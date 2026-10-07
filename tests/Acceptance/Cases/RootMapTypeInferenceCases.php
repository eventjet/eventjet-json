<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class RootMapTypeInferenceCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>|object, null, (Closure(): JsonType<list<mixed>|object>)}>
     * @throws DecodeError
     */
    public static function values(): iterable
    {
        foreach ([
            'people map' => [self::people(), static fn(): JsonType => JsonType::map(Person::class)],
            'enum map' => [self::statuses(), static fn(): JsonType => JsonType::map(StringBackedStatus::class)],
            'map of lists' => [self::groups(), static fn(): JsonType => JsonType::map(JsonType::array(Person::class))],
            'list of maps' => [self::maps(), static fn(): JsonType => JsonType::array(JsonType::map(Person::class))],
            'map of maps' => [self::nested(), static fn(): JsonType => JsonType::map(JsonType::map(Person::class))],
        ] as $name => [$original, $type]) {
            if ($original instanceof DecodeError) {
                throw $original;
            }
            yield $name => [$original, null, $type];
        }
    }

    /** @return ArrayObject<string, Person>|DecodeError */
    private static function people(): ArrayObject|DecodeError
    {
        return Json::decode('{"author":{"firstName":"Ada","lastName":"Lovelace"}}', JsonType::map(Person::class));
    }

    /** @return ArrayObject<string, StringBackedStatus>|DecodeError */
    private static function statuses(): ArrayObject|DecodeError
    {
        return Json::decode('{"status":"ready"}', JsonType::map(StringBackedStatus::class));
    }

    /** @return ArrayObject<string, list<Person>>|DecodeError */
    private static function groups(): ArrayObject|DecodeError
    {
        return Json::decode(
            '{"group":[{"firstName":"Ada","lastName":"Lovelace"}]}',
            JsonType::map(JsonType::array(Person::class)),
        );
    }

    /** @return list<ArrayObject<string, Person>>|DecodeError */
    private static function maps(): array|DecodeError
    {
        return Json::decode(
            '[{"author":{"firstName":"Ada","lastName":"Lovelace"}}]',
            JsonType::array(JsonType::map(Person::class)),
        );
    }

    /** @return ArrayObject<string, ArrayObject<string, Person>>|DecodeError */
    private static function nested(): ArrayObject|DecodeError
    {
        return Json::decode(
            '{"group":{"author":{"firstName":"Ada","lastName":"Lovelace"}}}',
            JsonType::map(JsonType::map(Person::class)),
        );
    }
}
