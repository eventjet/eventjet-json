<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use SplObjectStorage;

use function array_pop;
use function array_reverse;
use function count;
use function get_object_vars;
use function get_resource_id;
use function is_array;
use function is_float;
use function is_nan;
use function is_object;
use function is_resource;
use function is_scalar;

/** @internal */
final readonly class ValueSnapshot
{
    /** @param list<string|int|float|bool|null> $tokens */
    private function __construct(private array $tokens)
    {
    }

    public static function capture(mixed $value): self|null
    {
        $tokens = [];
        /** @var SplObjectStorage<object, int> $objects */
        $objects = new SplObjectStorage();
        /** @var list<array{mixed, int, array-key|null}> $pending */
        $pending = [[$value, 0, null]];
        while (($entry = array_pop($pending)) !== null) {
            [$current, $depth, $key] = $entry;
            if ($depth > 512) {
                return null;
            }
            $tokens[] = $key;
            if ($current instanceof BackedEnum) {
                $current = $current->value;
            }
            if (is_object($current)) {
                if ($objects->contains($current)) {
                    $tokens[] = 'reference';
                    $tokens[] = $objects[$current];
                    continue;
                }
                $objects[$current] = count($objects);
                $tokens[] = 'object';
                $tokens[] = $current::class;
                $current = get_object_vars($current);
            }
            if (is_array($current)) {
                $tokens[] = 'array';
                $tokens[] = count($current);
                /** @var mixed $item */
                foreach (array_reverse($current, true) as $itemKey => $item) {
                    $pending[] = [$item, $depth + 1, $itemKey];
                }
                continue;
            }
            if (is_resource($current)) {
                $tokens[] = 'resource';
                $tokens[] = get_resource_id($current);
                continue;
            }
            if (is_float($current) && is_nan($current)) {
                $tokens[] = 'nan';
                continue;
            }
            if (!is_scalar($current) && $current !== null) {
                return null;
            }
            $tokens[] = 'scalar';
            $tokens[] = $current;
        }
        return new self($tokens);
    }

    public function matches(mixed $value): bool
    {
        return self::capture($value)?->tokens === $this->tokens;
    }
}
