<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_array;
use function sprintf;

/** `contains`, with `minContains` and `maxContains`. @internal */
final readonly class ContainsKeyword implements Keyword
{
    public function __construct(
        private Node $schema,
        private int|float $min,
        private int|float|null $max,
        private bool $annotates,
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
        if (!is_array($value)) {
            return true;
        }
        $mark = $run->mark();
        $matches = 0;
        $kp = $keywordLocation . '/contains';
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            $ip = $run->collect ? $instanceLocation . '/' . $index : '';
            $match = $this->schema->evaluate($item, $run, $ip, $kp, null);
            if (!$match) {
                continue;
            }
            $matches++;
            if ($annotations !== null && $this->annotates) {
                $annotations->indices[$index] = true;
            }
        }
        $run->rollBack($mark);
        if ($matches < $this->min) {
            return $run->fail(
                $instanceLocation,
                $kp,
                $this->location,
                sprintf(
                    'At least %s items must match the "contains" schema, but %d do.',
                    (string) $this->min,
                    $matches,
                ),
            );
        }
        if ($this->max !== null && $matches > $this->max) {
            return $run->fail(
                $instanceLocation,
                $keywordLocation . '/maxContains',
                $this->location,
                sprintf('At most %s items may match the "contains" schema, but %d do.', (string) $this->max, $matches),
            );
        }
        return true;
    }
}
