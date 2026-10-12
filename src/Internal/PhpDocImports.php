<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;

use function file_get_contents;
use function is_file;

/** @internal */
final class PhpDocImports
{
    /** @var MetadataCache<array<string, string>>|null */
    private static MetadataCache|null $imports = null;

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    public static function forClass(ReflectionClass $class): array
    {
        /** @var MetadataCache<array<string, string>> $cache */
        $cache = self::$imports ?? new MetadataCache();
        self::$imports = $cache;

        return $cache->resolve(
            $class->getName(),
            /** @return array<string, string> */ static fn(): array => self::load($class),
        );
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    private static function load(ReflectionClass $class): array
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
        );
    }
}
