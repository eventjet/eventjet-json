<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Eventjet\Json\Internal\Schema\SchemaRegex;
use Override;

use function is_string;
use function sprintf;

/** @internal */
final readonly class PatternKeyword implements Keyword
{
    public function __construct(
        private SchemaRegex $regex,
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
        $matches = $this->regex->matches($value, $run);
        if ($matches) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/pattern',
            $this->regex->location,
            sprintf('The value must match the pattern %s.', $this->regex->pattern),
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
