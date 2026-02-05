<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ReflectionClass;

use function array_key_exists;
use function count;
use function explode;
use function file_get_contents;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function substr;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

final class UseStatementResolver
{
    /**
     * @var array<string, array<string, string>>
     */
    private static array $cache = [];

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Resolve a short class name to a fully qualified class name.
     */
    public function resolve(string $shortName, string $contextClass): string
    {
        if (str_contains($shortName, '\\')) {
            return $shortName;
        }

        $useStatements = $this->getUseStatements($contextClass);

        if (array_key_exists($shortName, $useStatements)) {
            return $useStatements[$shortName];
        }

        /** @var class-string $contextClass */
        $reflection = new ReflectionClass($contextClass);
        $namespace = $reflection->getNamespaceName();

        if ($namespace === '') {
            return $shortName;
        }

        return $namespace . '\\' . $shortName;
    }

    /**
     * @return array<string, string>
     */
    private function getUseStatements(string $className): array
    {
        if (array_key_exists($className, self::$cache)) {
            return self::$cache[$className];
        }

        /** @var class-string $className */
        $reflection = new ReflectionClass($className);
        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            self::$cache[$className] = [];
            return [];
        }

        $content = file_get_contents($fileName);
        if ($content === false) {
            self::$cache[$className] = [];
            return [];
        }

        $useStatements = $this->parseUseStatements($content);
        self::$cache[$className] = $useStatements;

        return $useStatements;
    }

    /**
     * @return array<string, string>
     */
    private function parseUseStatements(string $content): array
    {
        $classKeywordPos = $this->findClassKeyword($content);
        if ($classKeywordPos !== null) {
            $content = substr($content, 0, $classKeywordPos);
        }

        $pattern = '/^use\s+(?<fqcn>[^\s;]+)(?:\s+as\s+(?<alias>[^\s;]+))?;/m';
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $useStatements = [];
        foreach ($matches as $match) {
            $fqcn = $match['fqcn'];
            if (isset($match['alias']) && $match['alias'] !== '') { // @phpstan-ignore notIdentical.alwaysTrue
                $alias = $match['alias'];
            } else {
                $parts = explode('\\', $fqcn);
                $alias = $parts[count($parts) - 1];
            }
            $useStatements[$alias] = $fqcn;
        }

        return $useStatements;
    }

    private function findClassKeyword(string $content): int|null
    {
        $pattern = '/\b(?<keyword>class|interface|trait|enum)\s+\w+/';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        /** @var int<0, max> $offset */
        $offset = $matches[0][1];
        return $offset;
    }
}
