<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/**
 * `$dynamicRef` whose target has a matching `$dynamicAnchor`, which resolves to the outermost resource in the
 * dynamic scope with that anchor.
 *
 * @internal
 */
final readonly class DynamicRefKeyword implements Keyword
{
    public function __construct(
        private Node $target,
        private string $anchor,
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
            $candidate = $resource->dynamicAnchors[$this->anchor] ?? null;
            if ($candidate !== null) {
                $target = $candidate;
                break;
            }
        }
        return $target->evaluate($value, $run, $instanceLocation, $keywordLocation . '/$dynamicRef', $annotations);
    }
}
