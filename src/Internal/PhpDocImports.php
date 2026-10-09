<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;

use function file_get_contents;
use function is_file;
use function strpos;
use function substr;

/** @internal */
final class PhpDocImports
{
    /** @var array<string, MetadataCache<array<string, string>>> */
    private static array $imports = [];

    public static function sourceBeforeLine(string $source, int $line): string
    {
        if ($line <= 1) {
            return $source;
        }
        $lineOffset = 0;
        for ($currentLine = 1; $currentLine < $line; ++$currentLine) {
            $newline = strpos($source, needle: "\n", offset: $lineOffset);
            if ($newline === false) {
                return $source;
            }
            $lineOffset = $newline + 1;
        }
        return substr($source, offset: 0, length: $lineOffset);
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    public static function forClass(ReflectionClass $class, PhpDocImportKind $kind = PhpDocImportKind::ClassName): array
    {
        /** @var MetadataCache<array<string, string>> $cache */
        $cache = self::$imports[$kind->name] ?? new MetadataCache();
        self::$imports[$kind->name] = $cache;

        return $cache->resolve(
            $class->getName(),
            /** @return array<string, string> */ static fn(): array => self::load($class, $kind),
        );
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    private static function load(ReflectionClass $class, PhpDocImportKind $kind): array
    {
        $file = (string) $class->getFileName();
        $exists = is_file($file);
        if (!$exists) {
            return [];
        }

        $source = (string) file_get_contents($file);

        return PhpDocImportScanner::scan(
            $source,
            (int) $class->getStartLine(),
            $class->getShortName(),
            $class->getNamespaceName(),
            $kind,
        );
    }
}
