<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Item;
use Eventjet\Json\JsonError;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_is_list;
use function assert;
use function class_exists;
use function count;
use function enum_exists;
use function explode;
use function file;
use function interface_exists;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strcspn;
use function strlen;
use function substr;
use function trim;

/**
 * @internal
 */
final readonly class PhpType
{
    /**
     * @param list<self> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public CollectionType|null $collection = null,
    ) {
    }

    public static function target(string $type): self
    {
        return self::parse($type, new ReflectionClass(stdClass::class));
    }

    public static function parameter(ReflectionParameter $parameter): self
    {
        $class = $parameter->getDeclaringClass();
        assert($class !== null);
        $native = (string)$parameter->getType();
        $doc = $parameter->getDeclaringFunction()->getDocComment();
        $documented = $doc === false ? null : self::findParamTagType(self::parseDocTags($doc), $parameter->getName());
        if (str_contains($native, 'array') && $documented === null) {
            throw JsonError::decodeFailed('The type of the constructor parameter "' . $parameter->getName()
                . '" for class ' . $class->getName() . ' is "array", but its shape is not documented');
        }
        return self::declaration($native, $documented === null ? null : self::parse($documented, $class), $class);
    }

    public static function property(ReflectionProperty $property): self
    {
        foreach ($property->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($property->isPromoted() && $parameter->getName() === $property->getName()) {
                return self::parameter($parameter);
            }
        }
        $class = $property->getDeclaringClass();
        $native = (string)$property->getType();
        $item = ($property->getAttributes(Item::class)[0] ?? null)?->newInstance();
        if ($item !== null) {
            return self::declaration($native, new self('collection', collection: new CollectionType(false, new self($item->type))), $class);
        }
        $doc = $property->getDocComment();
        if ($doc !== false && preg_match('/@var\s+([^\r\n*]+)/', $doc, $matches) === 1) {
            return self::declaration($native, self::parse(self::docType($matches[1]), $class), $class);
        }
        return self::declaration($native, null, $class);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    private static function declaration(string $native, self|null $documented, ReflectionClass $class): self
    {
        $type = self::parse($native === '' ? 'mixed' : $native, $class);
        if ($documented === null) {
            return $type;
        }
        if (!$documented->isSubtypeOf($type)) {
            throw JsonError::decodeFailed('PHPDoc type is incompatible with native type "' . $native . '"');
        }
        return $documented;
    }

    private static function docType(string $text): string
    {
        $type = preg_replace(['/\s*([<,|&])\s*/', '/\s+>/'], ['$1', '>'], trim($text));
        assert($type !== null);
        return substr($type, 0, strcspn($type, " \t\r\n"));
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    private static function parse(string $text, ReflectionClass $class): self
    {
        $text = preg_replace('/\s+/', '', $text);
        assert($text !== null);
        if (str_starts_with($text, '?')) {
            $text = substr($text, 1) . '|null';
        }
        $parts = self::split($text, '|');
        if (count($parts) > 1) {
            $arguments = [];
            foreach ($parts as $part) {
                $arguments[] = self::parse($part, $class);
            }
            return new self('union', $arguments);
        }
        if (preg_match('/^(non-empty-list|list|array)<(.*)>$/', $text, $matches) === 1) {
            $arguments = [];
            foreach (self::split($matches[2], ',') as $part) {
                if ($part === '') {
                    throw JsonError::decodeFailed('Invalid collection type "' . $text . '"');
                }
                $arguments[] = self::parse($part, $class);
            }
            $list = $matches[1] !== 'array';
            if (($list && count($arguments) !== 1) || (!$list && count($arguments) !== 1 && count($arguments) !== 2)) {
                throw JsonError::decodeFailed('Invalid collection type "' . $text . '"');
            }
            if (count($arguments) === 2) {
                return new self('collection', collection: new CollectionType(true, $arguments[1]));
            }
            return new self('collection', collection: new CollectionType($list ? false : null, $arguments[0]));
        }
        if ($text === 'array') {
            return new self('collection', collection: new CollectionType(null, new self('mixed')));
        }
        $name = match ($text) {
            'positive-int', 'non-negative-int' => 'int',
            'non-empty-string', 'numeric-string' => 'string',
            'self', 'static' => $class->getName(),
            default => $text,
        };
        if (str_contains($name, '&')) {
            return new self('intersection');
        }
        if (preg_match('/^(mixed|string|int|float|bool|true|false|null|array|array-key|object|callable|iterable)$/', $name) === 1) {
            return new self($name);
        }
        if ($name === $class->getName() || class_exists($name) || enum_exists($name)) {
            return new self($name);
        }
        return new self(self::aliasToFqcn($name, $class));
    }

    /**
     * @return list<string>
     */
    private static function split(string $text, string $separator): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        for ($index = 0; $index < strlen($text); $index++) {
            if ($text[$index] === '<') {
                $depth++;
            } elseif ($text[$index] === '>') {
                $depth--;
            } elseif ($text[$index] === $separator && $depth === 0) {
                $parts[] = substr($text, $start, $index - $start);
                $start = $index + 1;
            }
        }
        $parts[] = substr($text, $start);
        return $parts;
    }

    /**
     * @return list<array{string, string}>
     */
    private static function parseDocTags(string $doc): array
    {
        $tags = [];
        $lines = explode("\n", substr(trim($doc), 3, -2));
        $currentTag = null;
        $currentTagContent = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '* ')) {
                $line = substr($line, 2);
            }
            $result = preg_match('/^@(\w+)(?:\s+(.*))?/', $line, $matches);
            if ($result === 1) {
                if ($currentTag !== null) {
                    $tags[] = [$currentTag, $currentTagContent];
                }
                $currentTag = $matches[1];
                $currentTagContent = $matches[2] ?? '';
                continue;
            }
            if ($currentTag === null) {
                continue;
            }
            $currentTagContent .= ' ' . $line;
        }
        if ($currentTag !== null) {
            $tags[] = [$currentTag, $currentTagContent];
        }
        return $tags;
    }

    /**
     * @param list<array{string, string}> $tags
     */
    private static function findParamTagType(array $tags, string $param): string|null
    {
        foreach ($tags as [$tag, $content]) {
            if ($tag !== 'param') {
                continue;
            }
            $result = preg_match('/^(?<type>.+)\s+\$(?<name>\S+)/', $content, $matches);
            if ($result !== 1) {
                continue;
            }
            if ($matches['name'] !== $param) {
                continue;
            }
            return $matches['type'];
        }
        return null;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    private static function aliasToFqcn(string $alias, ReflectionClass $class): string
    {
        $namespace = $class->getNamespaceName();
        if (str_starts_with($alias, '\\')) {
            return $alias;
        }
        if (str_contains($alias, '\\')) {
            return $namespace . '\\' . $alias;
        }
        $classFile = $class->getFileName();
        // Skip this check for now. If we ever encounter it (e.g. when dealing with built-in classes), we can add it
        // later. Should be pretty easy.
        if ($classFile === false) {
            return $alias;
        }
        $useStatements = self::parseUseStatements($classFile);
        if (isset($useStatements[$alias])) {
            return $useStatements[$alias];
        }
        return $namespace . '\\' . $alias;
    }

    /**
     * @return array<string, string>
     */
    private static function parseUseStatements(string $file): array
    {
        $useStatements = [];
        $lines = file($file);
        assert($lines !== false);
        foreach ($lines as $line) {
            $result = preg_match('/use\s+(?<ns>.+\\\\)?(?<class>.+?)(\s+as\s+(?<alias>.+))?;/', $line, $matches);
            if ($result !== 1) {
                continue;
            }
            $useStatements[$matches['alias'] ?? $matches['class']] = $matches['ns'] . $matches['class'];
        }
        return $useStatements;
    }

    public function accepts(mixed $value, JsonNode|null $source = null): bool
    {
        if ($this->collection !== null) {
            if (!is_array($value)) {
                return false;
            }
            $source = $source !== null && is_array($source->value) ? $source : null;
            $object = !array_is_list($value) ? true : $source?->object;
            if ($object !== null && !$this->collection->acceptsShape($object)) {
                return false;
            }
            return $this->collection->acceptsItems($value, $source);
        }
        if ($this->name === 'union') {
            foreach ($this->arguments as $option) {
                if ($option->accepts($value, $source)) {
                    return true;
                }
            }
            return false;
        }
        return $this->acceptsScalar($value)
            ?? (is_object($value) && class_exists($this->name) && is_a($value, $this->name));
    }

    public function acceptsScalar(mixed $value): bool|null
    {
        return match ($this->name) {
            'mixed' => true,
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'null' => $value === null,
            default => null,
        };
    }

    private function isSubtypeOf(self $native): bool
    {
        if ($native->name === 'mixed') {
            return true;
        }
        if ($this->name === 'union') {
            foreach ($this->arguments as $option) {
                if (!$option->isSubtypeOf($native)) {
                    return false;
                }
            }
            return true;
        }
        if ($native->name === 'union') {
            foreach ($native->arguments as $option) {
                if ($this->isSubtypeOf($option)) {
                    return true;
                }
            }
            return false;
        }
        if ($this->name === $native->name) {
            return true;
        }
        return match ($native->name) {
            'float' => $this->name === 'int',
            'bool' => $this->name === 'true' || $this->name === 'false',
            'object' => class_exists($this->name),
            default => class_exists($this->name)
                && (class_exists($native->name) || interface_exists($native->name))
                && is_a($this->name, $native->name, true),
        };
    }
}
