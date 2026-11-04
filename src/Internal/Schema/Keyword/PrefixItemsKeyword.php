<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_array;

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
        $count = 0;
        foreach ($this->schemas as $index => $schema) {
            if (!\array_key_exists($index, $value)) {
                break;
            }
            $count++;
            $ip = $run->collect ? $instanceLocation . '/' . $index : '';
            $kp = $run->collect ? $keywordLocation . '/' . $this->name . '/' . $index : '';
            $matches = $schema->evaluate($value[$index], $run, $ip, $kp, null);
            if ($matches) {
                continue;
            }
            $valid = false;
            if (!$run->collect) {
                return false;
            }
        }
        if ($annotations !== null && $count > $annotations->items) {
            $annotations->items = $count;
        }
        return $valid;
    }
}
