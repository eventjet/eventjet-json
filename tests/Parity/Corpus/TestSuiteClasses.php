<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function array_values;
use function class_exists;
use function dirname;
use function get_declared_classes;
use function preg_match;
use function realpath;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Finds the classes that the test suite declares: every class under `tests/`, and the classes that the case classes
 * under `tests/Acceptance/Cases` create, including the generated fixture classes and anonymous classes.
 *
 * @internal
 */
final class TestSuiteClasses
{
    private const string NAMESPACE = 'Eventjet\\Json\\Test\\';
    private const string CASES = '/Acceptance/Cases';

    /**
     * Loads the classes and returns every declared class outside `vendor/`.
     *
     * @return list<ReflectionClass<object>>
     * @throws \ReflectionException
     */
    public static function load(): array
    {
        $tests = self::directory();
        $classes = [];
        // Checking for a class runs the autoloader, which also declares the interfaces and traits under tests/.
        foreach (self::classFiles($tests) as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $classes[$class] = new ReflectionClass($class);
        }
        foreach (self::classFiles($tests . self::CASES) as $class) {
            CaseProviders::run($class);
        }
        $vendor = dirname($tests) . '/vendor/';
        foreach (get_declared_classes() as $class) {
            $reflection = new ReflectionClass($class);
            $file = $reflection->getFileName();
            $vendored = $file !== false && str_starts_with($file, $vendor);
            if ($reflection->isUserDefined() && !$vendored) {
                $classes[$class] = $reflection;
            }
        }
        return array_values($classes);
    }

    private static function directory(): string
    {
        $tests = realpath(__DIR__ . '/../..');
        return $tests === false ? __DIR__ . '/../..' : $tests;
    }

    /**
     * Returns the class names of the PHP files in a directory under `tests/`.
     *
     * @return list<string>
     */
    private static function classFiles(string $directory): array
    {
        $tests = self::directory();
        $classes = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            $relative = substr($file->getPathname(), offset: strlen($tests) + 1);
            $match = [];
            preg_match('~\A((?:[A-Z][A-Za-z0-9]*+/)*+[A-Z][A-Za-z0-9]*+)\.php\z~', $relative, $match);
            $name = $match[1] ?? '';
            if ($name !== '') {
                $classes[] = self::NAMESPACE . str_replace(search: '/', replace: '\\', subject: $name);
            }
        }
        return $classes;
    }
}
