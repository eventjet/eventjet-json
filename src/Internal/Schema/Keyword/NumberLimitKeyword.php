<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_float;
use function is_int;
use function sprintf;

/** @internal */
final readonly class NumberLimitKeyword implements Keyword
{
    public function __construct(
        private string $name,
        private int|float $limit,
        private bool $maximum,
        private bool $exclusive,
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
        if (!is_int($value) && !is_float($value)) {
            return true;
        }
        $ok = match (true) {
            $this->maximum && $this->exclusive => $value < $this->limit,
            $this->maximum => $value <= $this->limit,
            $this->exclusive => $value > $this->limit,
            default => $value >= $this->limit,
        };
        if ($ok) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/' . $this->name,
            $this->location,
            sprintf(
                'The value must be %s %s.',
                match (true) {
                    $this->maximum && $this->exclusive => 'less than',
                    $this->maximum => 'at most',
                    $this->exclusive => 'greater than',
                    default => 'at least',
                },
                (string) $this->limit,
            ),
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
