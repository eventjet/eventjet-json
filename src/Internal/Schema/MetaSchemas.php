<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use JsonException;
use stdClass;

use function file_get_contents;
use function json_decode;
use function str_replace;

use const JSON_THROW_ON_ERROR;

/**
 * The official meta-schemas, which ship with the package so that references to them work without a loader.
 *
 * @internal
 */
final class MetaSchemas
{
    /** The meta-schemas of the vocabularies, which the meta-schemas of the drafts are made of. */
    private const array VOCABULARIES = [
        'https://json-schema.org/draft/2019-09/meta/core' => Draft::Draft201909,
        'https://json-schema.org/draft/2019-09/meta/applicator' => Draft::Draft201909,
        'https://json-schema.org/draft/2019-09/meta/validation' => Draft::Draft201909,
        'https://json-schema.org/draft/2019-09/meta/meta-data' => Draft::Draft201909,
        'https://json-schema.org/draft/2019-09/meta/format' => Draft::Draft201909,
        'https://json-schema.org/draft/2019-09/meta/content' => Draft::Draft201909,
        'https://json-schema.org/draft/2020-12/meta/core' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/applicator' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/unevaluated' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/validation' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/meta-data' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/format-annotation' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/format-assertion' => Draft::Draft202012,
        'https://json-schema.org/draft/2020-12/meta/content' => Draft::Draft202012,
    ];

    /**
     * Returns the document and draft of the official meta-schema with the given URI, or null if there is none.
     *
     * @return array{stdClass, Draft}|null
     * @throws SchemaError If the file of the meta-schema can't be read.
     */
    public static function load(string $uri): array|null
    {
        $uri = Uri::withoutEmptyFragment($uri);
        $draft = self::VOCABULARIES[$uri] ?? Draft::ofMetaSchema($uri);
        if ($draft === null) {
            return null;
        }
        $file =
            str_replace(['http://json-schema.org/', 'https://json-schema.org/draft/'], replace: '', subject: $uri)
            . '.json';
        $path = __DIR__ . '/../../../resources/meta-schemas/' . $file;
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
        return [$document, $draft];
    }
}
