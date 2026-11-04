<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use InvalidArgumentException;
use RuntimeException;

use function file_get_contents;
use function get_debug_type;

/**
 * Valid schema documents of every draft, and the official meta-schema that each must match.
 *
 * @internal
 */
final class SchemaDocumentCases
{
    private const array DRAFTS = [Draft4::class, Draft6::class, Draft7::class, Draft201909::class, Draft202012::class];

    /**
     * Valid schema documents and their draft classes.
     *
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>}>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function documents(): iterable
    {
        foreach (MetaSchemaCases::documents() as $name => [$json, $class]) {
            yield $name => [$json, self::draftClass($class)];
        }
        foreach (SchemaCases::documents() as $name => [$schema, $json]) {
            if ($json === '{}') {
                continue;
            }
            yield $name => [$json, self::draftClass($schema::class)];
        }
    }

    /**
     * The official meta-schema of the draft of a decoded schema.
     *
     * @throws RuntimeException
     */
    public static function metaSchema(Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError $schema): string
    {
        $file = match (true) {
            $schema instanceof Draft4 => 'draft-04/schema.json',
            $schema instanceof Draft6 => 'draft-06/schema.json',
            $schema instanceof Draft7 => 'draft-07/schema.json',
            $schema instanceof Draft201909 => '2019-09/schema.json',
            $schema instanceof Draft202012 => '2020-12/schema.json',
            default => throw new RuntimeException('There is no meta-schema for ' . get_debug_type($schema) . '.'),
        };
        $json = file_get_contents(__DIR__ . '/../../../resources/meta-schemas/' . $file);
        if ($json === false) {
            throw new RuntimeException('Could not read ' . $file . '.');
        }
        return $json;
    }

    /**
     * @return class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>
     * @throws RuntimeException
     */
    private static function draftClass(string $class): string
    {
        foreach (self::DRAFTS as $draft) {
            if ($class === $draft) {
                return $draft;
            }
        }
        throw new RuntimeException($class . ' is not a draft class.');
    }
}
