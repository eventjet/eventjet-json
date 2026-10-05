<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;

use function file_get_contents;
use function is_file;

/** @internal */
final class PhpDocImports
{
    /**
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    public static function forClass(ReflectionClass $class): array
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
