<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** `$ref`, and `$dynamicRef` and `$recursiveRef` where they act like it. @internal */
final readonly class RefKeyword implements Keyword
{
    public function __construct(
        private string $name,
        private Node $target,
    ) {}

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        return $this->target->evaluate(
            $value,
            $run,
            $instanceLocation,
            $keywordLocation . '/' . $this->name,
            $annotations,
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [$this->target];
    }
}
