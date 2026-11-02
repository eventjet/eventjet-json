<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function preg_match;
use function str_contains;
use function str_ends_with;
use function substr;

/**
 * A doc comment or a standalone type to compare.
 *
 * @internal
 */
final readonly class ParityInput
{
    private function __construct(
        public string $source,
        public bool $isType,
    ) {}

    public static function doc(string $doc): self
    {
        return new self($doc, isType: false);
    }

    public static function type(string $type): self
    {
        return new self($type, isType: true);
    }

    /**
     * Whether PHP could return this as a doc comment: PHP's lexer starts one with `/**` and a space, tab, or line end,
     * and ends it at the first `*` and `/`.
     */
    public function isDocComment(): bool
    {
        return (
            !$this->isType
            && preg_match('~\A/\*\*[ \t\r\n]~', $this->source) === 1
            && str_ends_with($this->source, '*/')
            && !str_contains(substr($this->source, offset: 2, length: -2), '*/')
        );
    }
}
