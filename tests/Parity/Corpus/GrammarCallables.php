<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function implode;

/**
 * Builds callable and conditional types for {@see GrammarTypes}, which the decoder does not support but whose end
 * decides where a tag ends.
 *
 * @internal
 */
final readonly class GrammarCallables
{
    private const array RETURNS = [
        '[1]',
        'array(1)',
        '[1 => 2]',
        '$this[]',
        '$this',
        '(int)[]',
        'Foo<int>[]',
        'array{int}[]',
        'object{a: int}',
        'Foo::BAR[]',
    ];

    public function __construct(
        private Dice $dice,
        private GrammarTypes $types,
    ) {}

    public function callable(int $depth): string
    {
        $name = $this->dice->pick(['callable', '\Closure', 'Closure', 'pure-callable', 'Foo']);
        $templates = $this->dice->either(
            15,
            '<T' . $this->dice->pick(['', ' of int', ' = string', ' super int']) . '>',
        );
        $parameters = [];
        for ($parameter = $this->dice->int(0, 2); $parameter > 0; $parameter--) {
            $parameters[] =
                $this->types->type($depth + 1) . $this->dice->pick(['', ' $x', ' &$x', ' ...$x', ' $x=', '=', ' ...']);
        }
        $roll = $this->dice->int(1, 100);
        $return = match (true) {
            $roll <= 10 => '',
            $roll <= 17 => ': ' . $this->dice->pick(self::RETURNS),
            default => $this->dice->pick([': ', ':']) . $this->types->type($depth + 1),
        };
        return $name . $templates . '(' . implode(', ', $parameters) . ')' . $return;
    }

    public function conditional(int $depth): string
    {
        $variable = $this->dice->chance(50);
        $subject = $variable ? '$x' : $this->types->type($depth + 1);
        $negation = $this->dice->either(20, 'not ');
        return (
            '('
            . $subject
            . ' is '
            . $negation
            . $this->types->type($depth + 1)
            . ' ? '
            . $this->types->type($depth + 1)
            . ' : '
            . $this->types->type($depth + 1)
            . ')'
        );
    }
}
