<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/**
 * `$recursiveRef` whose target has `"$recursiveAnchor": true`, which resolves to the outermost resource in the
 * dynamic scope that has it too.
 *
 * @internal
 */
final readonly class RecursiveRefKeyword implements Keyword
{
    public function __construct(
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
        $target = $this->target;
        foreach ($run->scope as $resource) {
            if ($resource->rootNode === null) {
                continue;
            }

            $target = $resource->rootNode;
            break;
        }
        return $target->evaluate($value, $run, $instanceLocation, $keywordLocation . '/$recursiveRef', $annotations);
    }

    #[Override]
    public function inPlace(): array
    {
        return [$this->target];
    }
}
