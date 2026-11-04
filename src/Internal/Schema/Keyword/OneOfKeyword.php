<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function sprintf;

/** @internal */
final readonly class OneOfKeyword implements Keyword
{
    /** @param list<Node> $schemas */
    public function __construct(
        private array $schemas,
        private string $location,
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
        $matches = 0;
        foreach ($this->schemas as $index => $schema) {
            $kp = $run->collect ? $keywordLocation . '/oneOf/' . $index : '';
            $match = $schema->evaluate($value, $run, $instanceLocation, $kp, $annotations);
            if (!$match) {
                continue;
            }
            $matches++;
            if ($matches > 1 && $annotations === null && !$run->collect) {
                break;
            }
        }
        if ($matches === 1) {
            $run->rollBack($mark);
            return true;
        }
        if ($matches > 1) {
            $run->rollBack($mark);
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/oneOf',
            $this->location,
            sprintf('The value must match exactly one of the "oneOf" schemas, but it matches %d.', $matches),
        );
    }
}
