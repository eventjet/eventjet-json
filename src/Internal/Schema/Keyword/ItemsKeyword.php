<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_array;

/** `items` with one schema, `additionalItems`, and `items` after `prefixItems`. @internal */
final readonly class ItemsKeyword implements Keyword
{
    public function __construct(
        private string $name,
        private Node $schema,
        private int $start,
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
        $kp = $keywordLocation . '/' . $this->name;
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if ($index < $this->start) {
                continue;
            }
            $ip = $run->path($instanceLocation, $index);
            $matches = $this->schema->evaluate($item, $run, $ip, $kp, null);
            if ($matches) {
                continue;
            }
            $valid = false;
        }
        if ($annotations !== null) {
            $annotations->allItems = true;
        }
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
