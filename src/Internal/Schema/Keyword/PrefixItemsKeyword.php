<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function array_key_exists;
use function is_array;
use function max;

/** `prefixItems`, and `items` with an array of schemas. @internal */
final readonly class PrefixItemsKeyword implements Keyword
{
    /** @param list<Node> $schemas */
    public function __construct(
        private string $name,
        private array $schemas,
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
        $valid = true;
        $index = 0;
        while (array_key_exists($index, $this->schemas) && array_key_exists($index, $value)) {
            $ip = $run->path($instanceLocation, $index);
            $kp = $run->path($keywordLocation, $this->name, $index);
            $matches = $this->schemas[$index]->evaluate($value[$index], $run, $ip, $kp, null);
            $index++;
            if ($matches) {
                continue;
            }
            $valid = false;
        }
        if ($annotations !== null) {
            $annotations->items = max($annotations->items, $index);
        }
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
