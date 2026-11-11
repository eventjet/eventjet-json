<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function file_get_contents;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strlen;
use function substr;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Every required and optional test of the official JSON Schema Test Suite for the drafts that have schema classes.
 *
 * @internal
 */
final class OfficialSuiteCases
{
    public const string SUITE = __DIR__ . '/../JsonSchemaTestSuite';

    /**
     * Cases proposed to the suite, in its layout, until it has them. Each one fails against a mutant that the suite
     * misses.
     */
    public const string PROPOSED = __DIR__ . '/Proposed';

    private const array DRAFTS = [
        'draft4' => Draft4::class,
        'draft6' => Draft6::class,
        'draft7' => Draft7::class,
        'draft2019-09' => Draft201909::class,
        'draft2020-12' => Draft202012::class,
    ];

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>, mixed, bool, array{assertFormats?: true}}>
     *     The schema document, the class for documents without `$schema`, the value, whether it is valid, and the
     *     named arguments for compiling that differ from the defaults.
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function cases(): iterable
    {
        $present = is_file(self::SUITE . '/README.md');
        if (!$present) {
            throw new RuntimeException('The JSON Schema Test Suite is missing. Run "git submodule update --init".');
        }
        yield from self::directoryCases(self::SUITE . '/tests', prefix: '');
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>, mixed, bool, array{assertFormats?: true}}>
     *     The same as {@see self::cases()}.
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function proposed(): iterable
    {
        yield from self::directoryCases(self::PROPOSED, prefix: 'proposed/');
    }

    /**
     * @return iterable<string, array{string, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>, mixed, bool, array{assertFormats?: true}}>
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function directoryCases(string $root, string $prefix): iterable
    {
        foreach (self::DRAFTS as $draft => $class) {
            foreach (self::files($root, $draft) as $file) {
                yield from self::fileCases($root, $file, $class, $prefix);
            }
        }
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @return iterable<string, array{string, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>, mixed, bool, array{assertFormats?: true}}>
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function fileCases(string $root, string $file, string $class, string $prefix): iterable
    {
        /** @var list<object{description: string, schema: mixed, tests: list<object{description: string, data: mixed, valid: bool}>}> $groups */
        $groups = json_decode(self::read($root . '/' . $file), flags: JSON_THROW_ON_ERROR);
        $options = str_contains($file, '/optional/format/') ? ['assertFormats' => true] : [];
        foreach ($groups as $group) {
            $schema = json_encode(
                $group->schema,
                JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            foreach ($group->tests as $test) {
                $name = sprintf('%s%s: %s: %s', $prefix, $file, $group->description, $test->description);
                yield $name => [$schema, $class, $test->data, $test->valid, $options];
            }
        }
    }

    /**
     * @return list<string>
     * @throws RuntimeException
     */
    private static function files(string $root, string $draft): array
    {
        $directory = $root . '/' . $draft;
        $exists = is_dir($directory);
        if (!$exists) {
            return [];
        }
        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            $isJson = $file->isFile() && str_ends_with($file->getFilename(), '.json');
            if (!$isJson) {
                continue;
            }
            $files[] = substr($file->getPathname(), strlen($root . '/'));
        }
        sort($files);
        return $files;
    }

    /** @throws RuntimeException */
    private static function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not read ' . $path . '.');
        }
        return $contents;
    }
}
