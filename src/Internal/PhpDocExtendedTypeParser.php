<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use UnexpectedValueException;

use function assert;

/** @internal */
final readonly class PhpDocExtendedTypeParser extends PhpDocTypeParser
{
    /** @throws UnexpectedValueException */
    #[Override]
    protected function namedType(int $depth): PhpDocType
    {
        $parenthesized = $this->tokens->consume('(');
        if ($parenthesized) {
            $type = $this->type($depth + 1);
            $closed = $this->tokens->consume(')');
            if (!$closed) {
                throw new UnexpectedValueException('Expected a closing PHPDoc parenthesis.');
            }
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
            [$key, $optional] = $this->tokensTupleKey();
            $entries[] = new PhpDocTupleEntry($this->type($depth), $key, $optional);
            $closed = $this->tokens->consume('}');
            if ($closed) {
                return $entries;
            }
            $hasNext = $this->tokens->consume(',');
        } while ($hasNext);

        throw new UnexpectedValueException('Expected a PHPDoc tuple separator or closing delimiter.');
    }

    /**
     * @return array{string|null, bool}
     * @throws UnexpectedValueException
     */
    private function tokensTupleKey(): array
    {
        assert(
            $this->tokens instanceof PhpDocExtendedTypeTokens,
            description: 'The extended parser always reads extended tokens.',
        );
        return $this->tokens->tupleKey();
    }
}
