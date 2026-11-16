<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** @internal */
final readonly class AllOfKeyword implements Keyword
{
    /** @param list<Node> $schemas */
    public function __construct(
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
        $valid = true;
        foreach ($this->schemas as $index => $schema) {
            $kp = $run->path($keywordLocation, 'allOf', $index);
            $matches = $schema->evaluate($value, $run, $instanceLocation, $kp, $annotations);
            if ($matches) {
                continue;
            }
            $valid = false;
        }
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return $this->schemas;
    }
}
