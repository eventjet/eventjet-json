<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Closure;
use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_string;
use function sprintf;

/** @internal */
final readonly class FormatKeyword implements Keyword
{
    /** @param Closure(string): bool $check */
    public function __construct(
        private string $format,
        private Closure $check,
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
        if (!is_string($value)) {
            return true;
        }
        $valid = ($this->check)($value);
        if ($valid) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/format',
            $this->location,
            sprintf('The value must be a valid %s.', $this->format),
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
