<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Formats;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\ContentKeyword;
use Eventjet\Json\Internal\Schema\Keyword\FormatKeyword;
use Eventjet\Json\Schema\SchemaError;

use function extension_loaded;
use function sprintf;

/**
 * Compiles `format`, if the vocabulary asserts it, and the content keywords of Draft 7, which assert there.
 *
 * @internal
 */
final class FormatKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(SchemaObject $schema): array
    {
        $keywords = [];
        $format = $schema->vocabulary->formatAssertion ? $schema->string('format') : null;
        $formatKeyword = $format === null ? null : self::format($schema, $format);
        if ($formatKeyword !== null) {
            $keywords[] = $formatKeyword;
        }
        if ($schema->draft !== Draft::Draft7) {
            return $keywords;
        }
        $base64 = $schema->get('contentEncoding') === 'base64';
        $json = $schema->get('contentMediaType') === 'application/json';
        if ($base64 || $json) {
            $keywords[] = new ContentKeyword(
                $base64,
                $json,
                $schema->at('contentEncoding'),
                $schema->at('contentMediaType'),
            );
        }
        return $keywords;
    }

    /**
     * Returns the keyword for a format, or null if the format is unknown.
     *
     * @throws SchemaError
     */
    private static function format(SchemaObject $schema, string $format): FormatKeyword|null
    {
        $needsIntl = Formats::needsIntl($format, $schema->draft);
        if ($needsIntl && !extension_loaded(extension: 'intl')) {
            throw new SchemaError(sprintf(
                'Checking the format %s at %s needs the intl extension.',
                $format,
                $schema->at('format'),
            ));
        }
        $check = Formats::checker($format, $schema->draft);
        return $check === null ? null : new FormatKeyword($format, $check, $schema->at('format'));
    }
}
