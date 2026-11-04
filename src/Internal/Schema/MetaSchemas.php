<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use JsonException;
use stdClass;

use function file_get_contents;
use function json_decode;
use function rtrim;

use const JSON_THROW_ON_ERROR;

/**
 * The official meta-schemas, which ship with the package so that references to them work without a loader.
 *
 * @internal
 */
final class MetaSchemas
{
    private const array FILES = [
        'http://json-schema.org/draft-04/schema' => ['draft-04/schema.json', Draft::Draft4],
        'http://json-schema.org/draft-06/schema' => ['draft-06/schema.json', Draft::Draft6],
        'http://json-schema.org/draft-07/schema' => ['draft-07/schema.json', Draft::Draft7],
        'https://json-schema.org/draft/2019-09/schema' => ['2019-09/schema.json', Draft::Draft201909],
        'https://json-schema.org/draft/2019-09/meta/core' => ['2019-09/meta/core.json', Draft::Draft201909],
        'https://json-schema.org/draft/2019-09/meta/applicator' => [
            '2019-09/meta/applicator.json',
            Draft::Draft201909,
        ],
        'https://json-schema.org/draft/2019-09/meta/validation' => [
            '2019-09/meta/validation.json',
            Draft::Draft201909,
        ],
        'https://json-schema.org/draft/2019-09/meta/meta-data' => [
            '2019-09/meta/meta-data.json',
            Draft::Draft201909,
        ],
        'https://json-schema.org/draft/2019-09/meta/format' => ['2019-09/meta/format.json', Draft::Draft201909],
        'https://json-schema.org/draft/2019-09/meta/content' => ['2019-09/meta/content.json', Draft::Draft201909],
        'https://json-schema.org/draft/2020-12/schema' => ['2020-12/schema.json', Draft::Draft202012],
        'https://json-schema.org/draft/2020-12/meta/core' => ['2020-12/meta/core.json', Draft::Draft202012],
        'https://json-schema.org/draft/2020-12/meta/applicator' => [
            '2020-12/meta/applicator.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/unevaluated' => [
            '2020-12/meta/unevaluated.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/validation' => [
            '2020-12/meta/validation.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/meta-data' => [
            '2020-12/meta/meta-data.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/format-annotation' => [
            '2020-12/meta/format-annotation.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/format-assertion' => [
            '2020-12/meta/format-assertion.json',
            Draft::Draft202012,
        ],
        'https://json-schema.org/draft/2020-12/meta/content' => ['2020-12/meta/content.json', Draft::Draft202012],
    ];

    /**
     * Returns the document and draft of the official meta-schema with the given URI, or null if there is none.
     *
     * @return array{stdClass, Draft}|null
     * @throws SchemaError If the file of the meta-schema can't be read.
     */
    public static function load(string $uri): array|null
    {
        $entry = self::FILES[rtrim($uri, characters: '#')] ?? null;
        if ($entry === null) {
            return null;
        }
        $path = __DIR__ . '/../../../resources/meta-schemas/' . $entry[0];
        $json = file_get_contents($path);
        if ($json === false) {
            throw new SchemaError('Could not read the meta-schema ' . $path . '.');
        }
        try {
            /** @var stdClass $document */
            $document = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SchemaError('Could not parse the meta-schema ' . $path . '.', $exception);
        }
        return [$document, $entry[1]];
    }
}
