<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class RootArrayTypeInferenceCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{list<mixed>, null, (Closure(): JsonType<list<mixed>>)}>
     * @throws DecodeError
     */
    public static function values(): iterable
    {
        foreach (self::decodedValues() as $name => [$original, $type]) {
            if ($original instanceof DecodeError) {
                throw $original;
            }

            yield $name => [$original, null, $type];
        }
    }

    /** @return array<string, array{list<mixed>|DecodeError, (Closure(): JsonType<list<mixed>>)}> */
    private static function decodedValues(): array
    {
        return [
            'class list' => [self::people(), static fn(): JsonType => JsonType::array(Person::class)],
            'nested class list' => [
                self::nestedPeople(),
                static fn(): JsonType => JsonType::array(JsonType::array(Person::class)),
            ],
            'string enum list' => [
                self::statuses(),
                static fn(): JsonType => JsonType::array(StringBackedStatus::class),
            ],
            'nested integer enum list' => [
                self::nestedStatuses(),
                static fn(): JsonType => JsonType::array(JsonType::array(IntBackedStatus::class)),
            ],
        ];
    }

    /** @return list<Person>|DecodeError */
    private static function people(): array|DecodeError
    {
        return Json::decode('[{"firstName":"Ada","lastName":"Lovelace"}]', JsonType::array(Person::class));
    }

    /** @return list<list<Person>>|DecodeError */
    private static function nestedPeople(): array|DecodeError
    {
        return Json::decode(
            '[[{"firstName":"Ada","lastName":"Lovelace"}]]',
            JsonType::array(JsonType::array(Person::class)),
        );
    }

    /** @return list<StringBackedStatus>|DecodeError */
    private static function statuses(): array|DecodeError
    {
        return Json::decode('["ready"]', JsonType::array(StringBackedStatus::class));
    }

    /** @return list<list<IntBackedStatus>>|DecodeError */
    private static function nestedStatuses(): array|DecodeError
    {
        return Json::decode('[[1]]', JsonType::array(JsonType::array(IntBackedStatus::class)));
    }
}
