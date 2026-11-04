<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_string;
use function preg_match;
use function sprintf;

/** @internal */
final readonly class PatternKeyword implements Keyword
{
    /** @param non-empty-string $pcre */
    public function __construct(
        private string $pcre,
        private string $pattern,
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
        $matches = preg_match($this->pcre, $value) === 1;
        if ($matches) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/pattern',
            $this->location,
            sprintf('The value must match the pattern %s.', $this->pattern),
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
