<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** `$ref`, and `$dynamicRef` and `$recursiveRef` where they act like it. @internal */
final class RefKeyword implements Keyword
{
    public Node|null $target = null;

    public function __construct(
        private readonly string $name,
    ) {}

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        $target = $this->target;
        return $target === null
        || $target->evaluate($value, $run, $instanceLocation, $keywordLocation . '/' . $this->name, $annotations);
    }
}
