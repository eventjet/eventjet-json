<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Closure;
use Eventjet\Json\Benchmark\Fixtures\Record;
use Eventjet\Json\Benchmark\Fixtures\RecordBatch;
use Eventjet\Json\Test\Acceptance\Cases\SupportedDocumentRoundTripCases;
use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function array_keys;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class DocumentWorkloads
{
    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::factories());
    }

    /** @return array<string, Closure(): DocumentWorkload> */
    private static function factories(): array
    {
        $factories = [];
        foreach (SupportedDocumentRoundTripCases::factories() as $name => $factory) {
            $factories[$name] = static function () use ($factory): DocumentWorkload {
                [$json, $class, $check] = $factory();
                return new DocumentWorkload($json, $class, $check(...));
            };
        }
        foreach ([0, 1, 100, 1000] as $size) {
            $factories['record batch ' . $size] =
                /** @throws JsonException */
                static fn(): DocumentWorkload => self::batch($size);
        }
        return $factories;
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function named(string $name): DocumentWorkload
    {
        $factories = self::factories();
        $factory = $factories[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException('Unknown document workload: ' . $name);
        }
        return $factory();
    }

    /** @throws JsonException */
    private static function batch(int $size): DocumentWorkload
    {
        $records = [];
        for ($index = 0; $index < $size; ++$index) {
            $records[] = new Record(
                $index,
                'record-' . $index,
                (float) ($index / 4) + 0.5,
                ($index % 2) === 0,
                ($index % 3) === 0 ? null : 'note-' . $index,
            );
        }
        return new DocumentWorkload(
            json_encode(new RecordBatch($records), JSON_THROW_ON_ERROR),
            RecordBatch::class,
            self::batchIsHydrated(...),
        );
    }

    private static function batchIsHydrated(object $value): bool
    {
        if (!$value instanceof RecordBatch) {
            return false;
        }
        return HydrationCheck::allInstancesAre($value->records, Record::class);
    }
}
