<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

use function array_shift;
use function current;
use function mb_str_split;
use function sprintf;

/**
 * @phpstan-type TokenType Token | string | int | float | bool | null
 */
final class Tokenizer
{
    private const OPEN_CURLY = '{';
    private const CLOSE_CURLY = '}';
    private const COLON = ':';
    private const COMMA = ',';
    private const OPEN_BRACKET = '[';
    private const CLOSE_BRACKET = ']';
    private const QUOTE = '"';
    private const BACKSLASH = '\\';

    private int $line = 1;
    private int $column = 1;

    /**
     * @param list<string> $chars
     */
    public function __construct(private array &$chars)
    {
    }

    /**
     * @return list<TokenLocation>
     */
    public static function tokenize(string $source): array
    {
        $chars = mb_str_split($source);
        return (new self($chars))->doTokenize();
    }

    /**
     * @return list<TokenLocation>
     */
    private function doTokenize(): array
    {
        $tokens = [];
        while (true) {
            $this->skipWhitespace();
            $char = current($this->chars);
            if ($char === false) {
                return $tokens;
            }
            $token = match ($char) {
                self::OPEN_CURLY => Token::OpenCurly,
                self::CLOSE_CURLY => Token::CloseCurly,
                self::COLON => Token::Colon,
                self::COMMA => Token::Comma,
                self::OPEN_BRACKET => Token::OpenBracket,
                self::CLOSE_BRACKET => Token::CloseBracket,
                default => null,
            };
            if ($token !== null) {
                $tokens[] = self::charToken($token);
                $this->next();
                continue;
            }
            if ($char === self::QUOTE) {
                $tokens[] = $this->readString();
                continue;
            }
            if ($char === '-' || ($char >= '0' && $char <= '9')) {
                $tokens[] = $this->readNumber();
                continue;
            }
            if ($char === 't') {
                $this->expect('t');
                $this->expect('r');
                $this->expect('u');
                $this->expect('e');
                $tokens[] = new TokenLocation(true, Span::create($this->line, $this->column - 4, $this->line, $this->column));
                continue;
            }
            if ($char === 'f') {
                $this->expect('f');
                $this->expect('a');
                $this->expect('l');
                $this->expect('s');
                $this->expect('e');
                $tokens[] = new TokenLocation(false, Span::create($this->line, $this->column - 5, $this->line, $this->column));
                continue;
            }
            if ($char === 'n') {
                $this->expect('n');
                $this->expect('u');
                $this->expect('l');
                $this->expect('l');
                $tokens[] = new TokenLocation(null, Span::create($this->line, $this->column - 3, $this->line, $this->column));
                continue;
            }
            throw SyntaxError::create(
                sprintf("Unexpected character '%s' at line %d, column %d", $char, $this->line, $this->column),
                $this->line,
                $this->column,
            );
        }
    }

    private function readString(): TokenLocation
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $string = '';
        $this->expect(self::QUOTE);
        while (true) {
            $char = current($this->chars);
            if ($char === self::QUOTE) {
                $this->next();
                break;
            }
            if ($char === false) {
                throw SyntaxError::create(
                    sprintf('Unexpected end of input at line %d, column %d', $this->line, $this->column),
                    $this->line,
                    $this->column,
                );
            }
            if ($char !== self::BACKSLASH) {
                $string .= $char;
                $this->next();
                continue;
            }
            $this->next();
            $char = current($this->chars);
            $string .= match ($char) {
                false => throw SyntaxError::create(
                    sprintf('Unexpected end of input at line %d, column %d', $this->line, $this->column),
                    $this->line,
                    $this->column,
                ),
                'n' => "\n",
                default => $char,
            };
            $this->next();
        }
        return new TokenLocation($string, Span::create($startLine, $startColumn, $this->line, $this->column));
    }

    private function next(): void
    {
        $shifted = array_shift($this->chars);
        if ($shifted === "\n") {
            $this->line++;
            $this->column = 1;
        } else {
            $this->column++;
        }
    }

    private function expect(string $char): void
    {
        $current = current($this->chars);
        if ($current !== $char) {
            throw SyntaxError::create(
                sprintf("Expected '%s' but got '%s'", $char, $current),
                $this->line,
                $this->column,
            );
        }
        $this->next();
    }

    private function readNumber(): TokenLocation
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $isFloat = false;
        $number = '';
        $char = current($this->chars);
        if ($char === '-') {
            $number .= '-';
            $this->next();
        }
        $char = current($this->chars);
        if ($char === '0') {
            $number .= '0';
            $this->next();
        } else {
            $number .= $this->readDigits();
        }
        $char = current($this->chars);
        if ($char === '.') {
            $isFloat = true;
            $number .= '.';
            $this->next();
            $number .= $this->readDigits();
        }
        $char = current($this->chars);
        if ($char === 'e' || $char === 'E') {
            $number .= 'e';
            $this->next();
            $char = current($this->chars);
            if ($char === '+' || $char === '-') {
                $number .= $char;
                $this->next();
            }
            $number .= $this->readDigits();
        }
        $token = $isFloat ? (float)$number : (int)$number;
        return new TokenLocation($token, Span::create($startLine, $startColumn, $this->line, $this->column));
    }

    private function readDigits(): string
    {
        $digits = '';
        while (true) {
            $char = current($this->chars);
            if ($char === false) {
                break;
            }
            if ($char < '0' || $char > '9') {
                break;
            }
            $digits .= $char;
            $this->next();
        }
        return $digits;
    }

    private function skipWhitespace(): void
    {
        while (true) {
            $char = current($this->chars);
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $this->next();
                continue;
            }
            break;
        }
    }

    private function charToken(Token|string|int|float|bool|null $token): TokenLocation
    {
        return new TokenLocation($token, Span::char($this->line, $this->column));
    }
}
