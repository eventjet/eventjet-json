<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

use function floatval;
use function strtr;

/**
 * Names the constant expressions of a type like the decoder does, and collects its number literals with the values
 * that PHPStan's ConstExprNodeResolver and TypeNodeResolver give them: `(int)` or `(float)` of the literal.
 *
 * @internal
 */
final class PhpStanConstants
{
    /** @var list<PhpStanNumber> */
    private array $numbers = [];

    /**
     * Returns the name of a constant expression. Numbers and constants keep their source spelling without digit
     * separators, and strings are quoted with only the quote and backslashes escaped.
     */
    public function name(ConstExprNode $node): string
    {
        return $this->read($node, bound: false);
    }

    /** Returns the name of a bound of an integer range such as `int<0, max>`. */
    public function bound(ConstExprNode $node): string
    {
        return $this->read($node, bound: true);
    }

    /** Returns the name of an array shape key. */
    public function key(ConstExprNode|IdentifierTypeNode|null $key): string|null
    {
        return match (true) {
            $key === null => null,
            $key instanceof IdentifierTypeNode => $key->name,
            default => $this->name($key),
        };
    }

    /** @return list<PhpStanNumber> */
    public function numbers(): array
    {
        return $this->numbers;
    }

    private function read(ConstExprNode $node, bool $bound): string
    {
        if ($node instanceof ConstExprStringNode) {
            $quote = $node->quoteType === ConstExprStringNode::SINGLE_QUOTED ? "'" : '"';
            return $quote . strtr($node->value, ['\\' => '\\\\', $quote => '\\' . $quote]) . $quote;
        }
        if ($node instanceof ConstExprIntegerNode) {
            $this->numbers[] = new PhpStanNumber($node->value, (int) $node->value, $bound);
        }
        if ($node instanceof ConstExprFloatNode) {
            $this->numbers[] = new PhpStanNumber($node->value, floatval($node->value), $bound);
        }
        return (string) $node;
    }
}
