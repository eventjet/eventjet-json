<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

use stdClass;

use function current;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function next;
use function sprintf;

/**
 * @phpstan-import-type TokenType from Tokenizer
 * @phpstan-type JsonValue string | int | float | stdClass | list<mixed> | bool | null
 */
final class Parser
{
    /**
     * @param list<TokenLocation> $tokens
     */
    public function __construct(private array &$tokens)
    {
    }

    /**
     * @return JsonValue
     */
    public static function parse(string $source): string|int|float|stdClass|array|bool|null
    {
        $tokens = Tokenizer::tokenize($source);
        return (new self($tokens))->parseValue();
    }

    /**
     * @return JsonValue
     */
    private function parseValue(): string|int|float|stdClass|array|bool|null
    {
        $token = current($this->tokens);
        if ($token === false) {
            throw SyntaxError::create('Unexpected end of input', 0, 0);
        }
        if ($token->token === null || is_string($token->token) || is_int($token->token) || is_float($token->token) || is_bool($token->token)) {
            $this->next();
            return $token->token;
        }
        return match ($token->token) {
            Token::OpenCurly => $this->parseObject(),
            Token::OpenBracket => $this->parseArray(),
            default => throw SyntaxError::create(
                sprintf('Unexpected token %s', Token::print($token->token)),
                $token->location->start->line,
                $token->location->start->column,
            ),
        };
    }

    private function parseObject(): stdClass
    {
        $object = new stdClass();
        $this->next();
        $first = true;
        while (true) {
            $token = current($this->tokens);
            if ($token === false) {
                throw SyntaxError::create('Unexpected end of input', 0, 0);
            }
            if ($token->token === Token::CloseCurly) {
                $this->next();
                return $object;
            }
            if (!$first) {
                if ($token->token !== Token::Comma) {
                    throw SyntaxError::create(
                        sprintf('Expected comma, got %s', Token::print($token->token)),
                        $token->location->start->line,
                        $token->location->start->column,
                    );
                }
                $this->next();
            }
            [$key, $value] = $this->parseObjectPair();
            $object->$key = $value;
            $first = false;
        }
    }

    /**
     * @return list<mixed>
     */
    private function parseArray(): array
    {
        $array = [];
        $this->next();
        $first = true;
        while (true) {
            $token = current($this->tokens);
            if ($token === false) {
                throw SyntaxError::create('Unexpected end of input', 0, 0);
            }
            if ($token->token === Token::CloseBracket) {
                $this->next();
                return $array;
            }
            if (!$first) {
                if ($token->token !== Token::Comma) {
                    throw SyntaxError::create(
                        sprintf('Expected comma, got %s', Token::print($token->token)),
                        $token->location->start->line,
                        $token->location->start->column,
                    );
                }
                $this->next();
            }
            $array[] = $this->parseValue();
            $first = false;
        }
    }

    private function next(): void
    {
        next($this->tokens);
    }

    /**
     * @return array{string, JsonValue}
     */
    private function parseObjectPair(): array
    {
        $token = current($this->tokens);
        if ($token === false) {
            throw SyntaxError::create('Unexpected end of input', 0, 0);
        }
        if (!is_string($token->token)) {
            throw SyntaxError::create(
                sprintf('Expected string, got %s', Token::print($token->token)),
                $token->location->start->line,
                $token->location->start->column,
            );
        }
        $key = $token->token;
        $this->next();
        $token = current($this->tokens);
        if ($token === false) {
            throw SyntaxError::create('Unexpected end of input', 0, 0);
        }
        if ($token->token !== Token::Colon) {
            throw SyntaxError::create(
                sprintf('Expected colon, got %s', Token::print($token->token)),
                $token->location->start->line,
                $token->location->start->column,
            );
        }
        $this->next();
        return [$key, $this->parseValue()];
    }
}
