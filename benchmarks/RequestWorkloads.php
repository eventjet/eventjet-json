<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\Schema\Validator;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function class_exists;

/**
 * The workloads that the CI script for fresh PHP-FPM requests times.
 *
 * @api Used by the PHP-FPM scripts in CI.
 */
final class RequestWorkloads
{
    /**
     * The compared source can be older than the frozen workloads, so workloads for a feature it lacks are left out.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            ...DocumentWorkloads::names(),
            ...(class_exists(Validator::class) ? SchemaWorkloads::names() : []),
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function named(string $name): RequestWorkload
    {
        return SchemaWorkloads::handles($name) ? SchemaWorkloads::named($name) : DocumentWorkloads::named($name);
    }
}
