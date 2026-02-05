<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Eventjet\Json\Exception\TypeParseException;
use Eventjet\Json\Exception\UnsupportedTypeException;

use function count;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Recursive descent parser for type strings.
 *
 * Grammar:
 *   Type       := UnionType | SimpleType
 *   UnionType  := SimpleType ('|' SimpleType)+
 *   SimpleType := 'list' '<' Type '>' | 'array' '<' 'string' ',' Type '>' | ClassName | Primitive
 *   Primitive  := 'string' | 'int' | 'float' | 'bool' | 'null'
 */
final class TypeParser
{
    /**
     * @var callable(string): string
     */
    private mixed $classResolver;

    /**
     * @param (callable(string): string)|null $classResolver Resolves short class names to FQCNs
     */
    public function __construct(
        callable|null $classResolver = null,
    ) {
        $this->classResolver = $classResolver ?? static fn(string $name): string => $name;
    }

    /**
     * @throws TypeParseException
     */
    public function parse(string $type): ParsedType
    {
        $type = trim($type);
        if ($type === '') {
            throw new TypeParseException('Empty type string');
        }

        return $this->parseType($type);
    }

    /**
     * @throws TypeParseException
     */
    private function parseType(string $type): ParsedType
    {
        $parts = $this->splitUnion($type);

        if (count($parts) === 1) {
            return $this->parseSimpleType($parts[0]);
        }

        $types = [];
        foreach ($parts as $part) {
            $types[] = $this->parseSimpleType($part);
        }

        return new UnionType($types);
    }

    /**
     * Split a type string on '|' while respecting angle bracket depth.
     *
     * @return list<string>
     * @throws TypeParseException
     */
    private function splitUnion(string $type): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($type);

        for ($i = 0; $i < $len; $i++) {
            $char = $type[$i];

            if ($char === '<') {
                $depth++;
                $current .= $char;
            } elseif ($char === '>') {
                $depth--;
                if ($depth < 0) {
                    throw new TypeParseException(sprintf('Unexpected ">" in type: %s', $type));
                }
                $current .= $char;
            } elseif ($char === '|' && $depth === 0) {
                $part = trim($current);
                if ($part === '') {
                    throw new TypeParseException(sprintf('Empty type in union: %s', $type));
                }
                $parts[] = $part;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($depth !== 0) {
            throw new TypeParseException(sprintf('Unclosed angle bracket in type: %s', $type));
        }

        $part = trim($current);
        if ($part === '') {
            throw new TypeParseException(sprintf('Empty type in union: %s', $type));
        }
        $parts[] = $part;

        return $parts;
    }

    /**
     * @throws TypeParseException
     */
    private function parseSimpleType(string $type): ParsedType
    {
        $type = trim($type);

        if (str_starts_with($type, 'list<')) {
            return $this->parseListType($type);
        }

        if (str_starts_with($type, 'array<')) {
            return $this->parseArrayType($type);
        }

        if ($this->isUnsupportedArraySyntax($type)) {
            throw new TypeParseException(
                sprintf('Unsupported array syntax "%s". Use list<T> instead.', $type),
            );
        }

        if (PrimitiveType::isPrimitive($type)) {
            return PrimitiveType::fromName($type);
        }

        $resolved = ($this->classResolver)($type);
        return new ClassType($resolved);
    }

    /**
     * @throws TypeParseException
     */
    private function parseListType(string $type): ListType
    {
        if (!str_starts_with($type, 'list<') || !str_ends_with($type, '>')) {
            throw new TypeParseException(sprintf('Invalid list type: %s', $type));
        }

        $inner = substr($type, 5, -1);
        $inner = trim($inner);

        if ($inner === '') {
            throw new TypeParseException('Empty inner type in list<>');
        }

        return new ListType($this->parseType($inner));
    }

    /**
     * @throws TypeParseException
     */
    private function parseArrayType(string $type): MapType
    {
        if (!str_starts_with($type, 'array<') || !str_ends_with($type, '>')) {
            throw new TypeParseException(sprintf('Invalid array type: %s', $type));
        }

        $inner = substr($type, 6, -1);
        $inner = trim($inner);

        if ($inner === '') {
            throw new TypeParseException('Empty inner type in array<>');
        }

        $commaPos = $this->findTopLevelComma($inner);
        if ($commaPos === null) {
            throw new UnsupportedTypeException(sprintf('array<> requires two type arguments: %s', $type));
        }

        $keyTypeStr = trim(substr($inner, 0, $commaPos));
        $valueTypeStr = trim(substr($inner, $commaPos + 1));

        if ($keyTypeStr === '') {
            throw new TypeParseException(sprintf('Empty key type in array<>: %s', $type));
        }

        if ($valueTypeStr === '') {
            throw new TypeParseException(sprintf('Empty value type in array<>: %s', $type));
        }

        if ($keyTypeStr !== 'string') {
            throw new TypeParseException(
                sprintf('Only string keys are supported in array<K, V>. Got "%s".', $keyTypeStr),
            );
        }

        return new MapType(
            PrimitiveType::string(),
            $this->parseType($valueTypeStr),
        );
    }

    /**
     * Find the position of a top-level comma in a type string, respecting angle bracket depth.
     */
    private function findTopLevelComma(string $type): int|null
    {
        $depth = 0;
        $len = strlen($type);

        for ($i = 0; $i < $len; $i++) {
            $char = $type[$i];

            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function isUnsupportedArraySyntax(string $type): bool
    {
        if (str_ends_with($type, '[]')) {
            return true;
        }

        if ($type === 'array') {
            return true;
        }

        return false;
    }
}
