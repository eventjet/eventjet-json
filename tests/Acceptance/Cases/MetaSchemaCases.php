<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use RuntimeException;

use function file_get_contents;

/**
 * The official meta-schemas of each JSON Schema draft, which together use every keyword of their draft.
 *
 * @internal
 */
final class MetaSchemaCases
{
    private const array META_SCHEMAS = [
        'draft-04/schema.json' => Draft4::class,
        'draft-06/schema.json' => Draft6::class,
        'draft-07/schema.json' => Draft7::class,
        '2019-09/schema.json' => Draft201909::class,
        '2019-09/meta/core.json' => Draft201909::class,
        '2019-09/meta/applicator.json' => Draft201909::class,
        '2019-09/meta/validation.json' => Draft201909::class,
        '2019-09/meta/meta-data.json' => Draft201909::class,
        '2019-09/meta/format.json' => Draft201909::class,
        '2019-09/meta/content.json' => Draft201909::class,
        '2020-12/schema.json' => Draft202012::class,
        '2020-12/meta/core.json' => Draft202012::class,
        '2020-12/meta/applicator.json' => Draft202012::class,
        '2020-12/meta/unevaluated.json' => Draft202012::class,
        '2020-12/meta/validation.json' => Draft202012::class,
        '2020-12/meta/meta-data.json' => Draft202012::class,
        '2020-12/meta/format-annotation.json' => Draft202012::class,
        '2020-12/meta/content.json' => Draft202012::class,
    ];

    /**
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     * @throws RuntimeException
     */
    public static function documents(): iterable
    {
        foreach (self::META_SCHEMAS as $path => $class) {
            $json = file_get_contents(__DIR__ . '/../NorthStar/JsonSchema/' . $path);
            if ($json === false) {
                throw new RuntimeException('Could not read meta-schema ' . $path . '.');
            }
            yield 'meta-schema ' . $path => [
                $json,
                $class,
                static fn(object $schema): bool => $schema instanceof $class,
            ];
        }
    }
}
