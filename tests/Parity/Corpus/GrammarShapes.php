<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function implode;

/**
 * Builds array, list, and object shapes for {@see GrammarTypes}, with keys of every kind, open shapes, and comments.
 *
 * @internal
 */
final readonly class GrammarShapes
{
    private const array SHAPE_KEYS = [
        '0',
        '1',
        '2',
        'a',
        "'a'",
        '"b"',
        'Foo::BAR',
        '0x0',
        '+1',
        '1_0',
        '01',
        'int',
        "'it\\'s'",
    ];

    public function __construct(
        private Dice $dice,
        private GrammarTypes $types,
    ) {}

    public function shape(int $depth): string
    {
        $items = [];
        for ($item = $this->dice->int(0, 3); $item > 0; $item--) {
            $key =
                $this->dice->pick(self::SHAPE_KEYS)
                . $this->dice->either(30, '?')
                . $this->dice->pick([': ', ':', ' : ']);
            $key = $this->dice->either(55, $key);
            $comment = $this->dice->either(5, "// note\n * ");
            $items[] = $comment . $key . $this->types->type($depth + 1);
        }
        $unsealed = $this->dice->chance(8);
        if ($unsealed) {
            $items[] = $this->dice->pick(['...', '...<int>', '...<int, string>', '... <string>']);
        }
        $kind = $this->dice->pick(['array', 'array', 'list', 'non-empty-array', 'non-empty-list', 'object', 'Array']);
        $open = $this->dice->either(6, ' {', '{');
        $trailing = $this->dice->either(10, ',');
        return (
            $kind
            . $open
            . $this->types->space()
            . implode($this->dice->pick([', ', ',', ",\n * "]), $items)
            . $trailing
            . $this->types->space()
            . '}'
        );
    }
}
