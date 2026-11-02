<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use function array_map;
use function implode;

/** @internal */
enum PhpType: string
{
    case Int = 'int';
    case Float = 'float';
    case String = 'string';
    case Bool = 'bool';
    case Null = 'null';
    case True = 'true';
    case False = 'false';

    /** @param self|PhpTypeExpression|class-string $item */
    public static function list(self|PhpTypeExpression|string $item): PhpTypeExpression
    {
        return new PhpTypeExpression('list<' . self::render($item) . '>');
    }

    /** @param self|PhpTypeExpression|class-string $value */
    public static function arrayObject(self|PhpTypeExpression|string $value): PhpTypeExpression
    {
        return new PhpTypeExpression('ArrayObject<string, ' . self::render($value) . '>');
    }

    /** @param self|PhpTypeExpression|class-string $value */
    public static function nonEmptyArray(self|PhpTypeExpression|string $value): PhpTypeExpression
    {
        return new PhpTypeExpression('non-empty-array<string, ' . self::render($value) . '>');
    }

    /** @param self|PhpTypeExpression|class-string ...$items */
    public static function tuple(self|PhpTypeExpression|string ...$items): PhpTypeExpression
    {
        return new PhpTypeExpression('array{' . implode(', ', array_map(self::render(...), $items)) . '}');
    }

    /**
     * @param self|PhpTypeExpression|class-string $first
     * @param self|PhpTypeExpression|class-string $second
     * @param self|PhpTypeExpression|class-string ...$rest
     */
    public static function union(
        self|PhpTypeExpression|string $first,
        self|PhpTypeExpression|string $second,
        self|PhpTypeExpression|string ...$rest,
    ): PhpTypeExpression {
        return new PhpTypeExpression(implode('|', array_map(self::render(...), [$first, $second, ...$rest])));
    }

    /** @param self|PhpTypeExpression|class-string $type */
    private static function render(self|PhpTypeExpression|string $type): string
    {
        return match (true) {
            $type instanceof self => $type->value,
            $type instanceof PhpTypeExpression => (string) $type,
            default => '\\' . $type,
        };
    }
}
