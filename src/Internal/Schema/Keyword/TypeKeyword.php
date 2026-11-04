<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;
use stdClass;

use function floor;
use function implode;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final readonly class TypeKeyword implements Keyword
{
    private const float INT_LIMIT = 9.223_372_036_854_775_807E18;

    /** @param list<string> $types */
    public function __construct(
        private array $types,
        private bool $floatsAreIntegersOnlyIfLarge,
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
        foreach ($this->types as $type) {
            if ($this->matches($type, $value)) {
                return true;
            }
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/type',
            $this->location,
            sprintf('The value must be of type %s.', implode(' or ', $this->types)),
        );
    }

    /** @mutation-free */
    private function matches(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'object' => $value instanceof stdClass,
            'array' => is_array($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value) || $this->isIntegralFloat($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }

    /** @mutation-free */
    private function isIntegralFloat(mixed $value): bool
    {
        if (!is_float($value) || !is_finite($value) || floor($value) !== $value) {
            return false;
        }
        return !$this->floatsAreIntegersOnlyIfLarge || $value >= self::INT_LIMIT || $value <= -self::INT_LIMIT;
    }
}
