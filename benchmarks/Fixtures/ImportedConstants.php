<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

use Eventjet\Json\Test\Acceptance\Cases\CollectionNameSource;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Static analyzers read bare constant names as class names, so this source is only compiled at runtime.
 *
 * @internal
 */
final class ImportedConstants
{
    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function record(): object
    {
        $class = CollectionNameSource::load(
            'Eventjet\\Json\\Benchmark\\ImportedConstants\\Record',
            'namespace Eventjet\\Json\\Benchmark\\ImportedConstants;'
            . ' use const Eventjet\\Json\\Benchmark\\ImportedConstants\\{LIMIT_LARGE as LARGE, LIMIT_SMALL as SMALL};'
            . ' const LIMIT_SMALL = 10;'
            . ' const LIMIT_LARGE = 100;'
            . ' final readonly class Record {'
            . ' /** @param list<SMALL|LARGE> $limits */'
            . ' public function __construct(public array $limits) {}'
            . ' }',
        );
        return new ReflectionClass($class)->newInstance([10, 100, 10]);
    }
}
