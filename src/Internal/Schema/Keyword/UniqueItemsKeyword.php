<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\JsonKey;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

/** @internal */
final readonly class UniqueItemsKeyword implements Keyword
{
    public function __construct(
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
        $seen = [];
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            $key = JsonKey::of($item);
            if (array_key_exists($key, $seen)) {
                return $run->fail(
                    $instanceLocation,
                    $keywordLocation . '/uniqueItems',
                    $this->location,
                    sprintf('The items must be unique, but items %d and %d are equal.', $seen[$key], $index),
                );
            }
            $seen[$key] = $index;
        }
        return true;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
