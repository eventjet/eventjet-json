<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use UnexpectedValueException;

use function count;
use function trim;

/** @internal */
final readonly class PhpDocTypeParser
{
    private function __construct(
        private PhpDocTypeTokens $tokens,
    ) {}

    public static function parse(string $source): PhpDocType|null
    {
        $parsed = self::prefix($source);

        return $parsed === null || trim($parsed[1], characters: " \t\r\n\v\f") !== '' ? null : $parsed[0];
    }

    /** @return array{PhpDocType, string}|null */
    public static function prefix(string $source): array|null
    {
        $parser = new self(new PhpDocTypeTokens($source));
        try {
            return [$parser->type(0), $parser->tokens->remainder()];
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    /** @throws UnexpectedValueException */
    private function type(int $depth): PhpDocType
    {
        if ($depth >= 64) {
            throw new UnexpectedValueException('PHPDoc nesting exceeds 64 type levels.');
        }

        return $this->compound($depth, '|');
    }

    /** @throws UnexpectedValueException */
    private function compound(int $depth, string $operator): PhpDocType
    {
        $types = [];

        do {
            $type = $operator === '|' ? $this->compound($depth, '&') : $this->namedType($depth);

            $types[] = $type;
            $hasNext = $this->tokens->consume($operator);
        } while ($hasNext);

        return count($types) === 1 ? $types[0] : new PhpDocType($operator, $types);
    }

    /** @throws UnexpectedValueException */
    private function namedType(int $depth): PhpDocType
    {
        $parenthesized = $this->tokens->consume('(');
        if ($parenthesized) {
            $type = $this->type($depth + 1);
            $this->tokens->expect(')');
            return $type;
        }
        $name = $this->tokens->token();
        $isShape = $this->tokens->consume('{');
        if ($isShape) {
            return new PhpDocType($name . '{}', entries: $this->entries($depth + 1));
        }
        $hasArguments = $this->tokens->consume('<');

        return $hasArguments ? new PhpDocType($name, $this->arguments($depth + 1)) : new PhpDocType($name);
    }

    /**
     * @return list<PhpDocType>
     * @throws UnexpectedValueException
     */
    private function arguments(int $depth): array
    {
        $arguments = [];
        do {
            $arguments[] = $this->type($depth);
            $closed = $this->tokens->consume('>');
            if ($closed) {
                return $arguments;
            }
            $hasNext = $this->tokens->consume(',');
        } while ($hasNext);

        throw new UnexpectedValueException('Expected a PHPDoc argument separator or closing delimiter.');
    }

    /**
     * @return list<PhpDocTupleEntry>
     * @throws UnexpectedValueException
     */
    private function entries(int $depth): array
    {
        $entries = [];
        do {
            $closed = $this->tokens->consume('}');
            if ($closed) {
                return $entries;
            }
            [$key, $optional] = $this->tokens->tupleKey();
            $entries[] = new PhpDocTupleEntry($this->type($depth), $key, $optional);
            $closed = $this->tokens->consume('}');
            if ($closed) {
                return $entries;
            }
            $hasNext = $this->tokens->consume(',');
        } while ($hasNext);

        throw new UnexpectedValueException('Expected a PHPDoc tuple separator or closing delimiter.');
    }
}
