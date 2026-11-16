<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

/**
 * The official meta-schemas, which ship with the package so that references to them work without a loader.
 *
 * @internal
 */
final class MetaSchemas
{
    /**
     * Returns the document and draft of the official meta-schema with the given URI, or null if there is none.
     *
     * @return array{stdClass, Draft}|null
     */
    public static function load(string $uri): array|null
    {
        $uri = Uri::withoutEmptyFragment($uri);
        /** @var array{stdClass, Draft}|null */
        return match ($uri) {
            'http://json-schema.org/draft-04/schema' => [
                require __DIR__ . '/../../../resources/meta-schemas/draft-04/schema.php',
                Draft::Draft4,
            ],
            'http://json-schema.org/draft-06/schema' => [
                require __DIR__ . '/../../../resources/meta-schemas/draft-06/schema.php',
                Draft::Draft6,
            ],
            'http://json-schema.org/draft-07/schema' => [
                require __DIR__ . '/../../../resources/meta-schemas/draft-07/schema.php',
                Draft::Draft7,
            ],
            'https://json-schema.org/draft/2019-09/schema' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/schema.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/core' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/core.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/applicator' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/applicator.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/validation' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/validation.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/meta-data' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/meta-data.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/format' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/format.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2019-09/meta/content' => [
                require __DIR__ . '/../../../resources/meta-schemas/2019-09/meta/content.php',
                Draft::Draft201909,
            ],
            'https://json-schema.org/draft/2020-12/schema' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/schema.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/core' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/core.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/applicator' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/applicator.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/unevaluated' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/unevaluated.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/validation' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/validation.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/meta-data' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/meta-data.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/format-annotation' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/format-annotation.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/format-assertion' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/format-assertion.php',
                Draft::Draft202012,
            ],
            'https://json-schema.org/draft/2020-12/meta/content' => [
                require __DIR__ . '/../../../resources/meta-schemas/2020-12/meta/content.php',
                Draft::Draft202012,
            ],
            default => null,
        };
    }
}
