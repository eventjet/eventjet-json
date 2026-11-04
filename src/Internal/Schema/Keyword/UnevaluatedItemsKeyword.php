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

/** @internal */
final readonly class UnevaluatedItemsKeyword implements Keyword
{
    public function __construct(
        private Node $schema,
    ) {}

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        if (!is_array($value) || $annotations === null || $annotations->allItems) {
            return true;
        }
        $valid = true;
        $kp = $keywordLocation . '/unevaluatedItems';
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if ($index < $annotations->items || array_key_exists($index, $annotations->indices)) {
                continue;
            }
            $ip = $run->path($instanceLocation, $index);
            $matches = $this->schema->evaluate($item, $run, $ip, $kp, null);
            if ($matches) {
                continue;
            }
            $valid = false;
            if (!$run->collect) {
                return false;
            }
        }
        $annotations->allItems = true;
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
