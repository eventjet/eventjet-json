<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** @internal */
final readonly class IfKeyword implements Keyword
{
    public function __construct(
        private Node $if,
        private Node|null $then,
        private Node|null $else,
    ) {}

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        $mark = $run->mark();
        $matches = $this->if->evaluate($value, $run, $instanceLocation, $keywordLocation . '/if', $annotations);
        $run->rollBack($mark);
        if ($matches) {
            return (
                $this->then === null
                || $this->then->evaluate($value, $run, $instanceLocation, $keywordLocation . '/then', $annotations)
            );
        }
        return (
            $this->else === null
            || $this->else->evaluate($value, $run, $instanceLocation, $keywordLocation . '/else', $annotations)
        );
    }
}
