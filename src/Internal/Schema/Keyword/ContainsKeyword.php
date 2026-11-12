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
    /**
     * @param int|float|null $min The value of `minContains`, or null if `contains` alone sets the minimum of 1.
     * @param string $location The absolute location of the schema that has the keywords.
     */
    public function __construct(
        private Node $schema,
        private int|float|null $min,
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
        $matches = 0;
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            $match = $run->quietly($this->schema, $item, null);
            if (!$match) {
                continue;
            }
            $matches++;
            if ($annotations !== null && $this->annotates) {
                $annotations->indices[$index] = true;
            }
        }
        $min = $this->min ?? 1;
        if ($matches < $min) {
            $keyword = $this->min === null ? 'contains' : 'minContains';
            return $run->fail(
                $instanceLocation,
                $keywordLocation . '/' . $keyword,
                $this->location . '/' . $keyword,
                sprintf('At least %s items must match the "contains" schema, but %d do.', (string) $min, $matches),
            );
        }
        if ($this->max !== null && $matches > $this->max) {
            return $run->fail(
                $instanceLocation,
                $keywordLocation . '/maxContains',
                $this->location . '/maxContains',
                sprintf('At most %s items may match the "contains" schema, but %d do.', (string) $this->max, $matches),
            );
        }
        return true;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
