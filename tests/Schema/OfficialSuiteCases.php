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
use function is_file;
use function json_decode;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;

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

    /** The drafts by the release number that the suite's `compatibility` field uses for them. */
    private const array DRAFTS = [
        4 => ['draft4', Draft4::class],
        6 => ['draft6', Draft6::class],
        7 => ['draft7', Draft7::class],
        2019 => ['draft2019-09', Draft201909::class],
        2020 => ['draft2020-12', Draft202012::class],
    ];

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{OfficialSuiteSchema, mixed, bool}> The schema, the value and whether it is valid.
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function cases(): iterable
    {
        $present = is_file(self::SUITE . '/README.md');
        if (!$present) {
            throw new RuntimeException('The JSON Schema Test Suite is missing. Run "git submodule update --init".');
        }
        yield from self::directoryCases(self::SUITE . '/validation/tests', prefix: '');
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{OfficialSuiteSchema, mixed, bool}> The same as {@see self::cases()}.
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function proposed(): iterable
    {
        yield from self::directoryCases(self::PROPOSED, prefix: 'proposed/');
    }

    /**
     * @return iterable<string, array{OfficialSuiteSchema, mixed, bool}>
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function directoryCases(string $root, string $prefix): iterable
    {
        $files = self::files($root);
        foreach (self::DRAFTS as $release => [$draft, $class]) {
            foreach ($files as $file) {
                /** @var object{tests: list<object{description: string, compatibility?: string, tags?: list<string>, schema: mixed, externalSchemas?: mixed, tests: list<object{description: string, data: mixed, valid: bool}>}>} $contents */
                $contents = json_decode(self::read($root . '/' . $file), flags: JSON_THROW_ON_ERROR);
                foreach ($contents->tests as $case) {
                    $compatible = OfficialSuiteCompatibility::includes($case->compatibility ?? null, $release);
                    if (!$compatible) {
                        continue;
                    }
                    $schema = OfficialSuiteSchema::ofCase($case, $class);
                    foreach ($case->tests as $test) {
                        $name = sprintf(
                            '%s%s [%s]: %s: %s',
                            $prefix,
                            $file,
                            $draft,
                            $case->description,
                            $test->description,
                        );
                        yield $name => [$schema, $test->data, $test->valid];
                    }
                }
            }
        }
    }

    /**
     * The test files below the root, without the proposals for future releases.
     *
     * @return list<string>
     */
    private static function files(string $root): array
    {
        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            $relative = substr($file->getPathname(), strlen($root . '/'));
            $isTest = $file->isFile() && str_ends_with($relative, '.json') && !str_starts_with($relative, 'proposals/');
            if (!$isTest) {
                continue;
            }
            $files[] = $relative;
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
