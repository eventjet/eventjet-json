<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use UnexpectedValueException;

use function count;
use function strpbrk;
use function trim;

/** @internal */
readonly class PhpDocTypeParser
{
    final protected function __construct(
        protected PhpDocTypeTokens $tokens,
    ) {}

    public static function parse(string $source): PhpDocType|null
    {
        $parsed = self::prefix($source);

        return $parsed === null || trim($parsed[1], characters: " \t\r\n\v\f") !== '' ? null : $parsed[0];
    }

    /** @return array{PhpDocType, string}|null */
    public static function prefix(string $source): array|null
    {
        $parser = strpbrk($source, characters: "'\"(:{0123456789") === false
            ? new self(new PhpDocTypeTokens($source))
            : new PhpDocExtendedTypeParser(new PhpDocExtendedTypeTokens($source));
        try {
            return [$parser->type(0), $parser->tokens->remainder()];
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    /** @throws UnexpectedValueException */
    protected function type(int $depth): PhpDocType
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
    protected function namedType(int $depth): PhpDocType
    {
        $name = $this->tokens->token();
        $hasArguments = $this->tokens->consume('<');

        return $hasArguments ? new PhpDocType($name, $this->arguments($depth + 1)) : new PhpDocType($name);
    }

    /**
     * @return list<PhpDocType>
     * @throws UnexpectedValueException
     */
    protected function arguments(int $depth): array
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
}
