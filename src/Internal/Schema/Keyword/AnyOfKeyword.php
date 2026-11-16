<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** @internal */
final readonly class AnyOfKeyword implements Keyword
{
    /** @param list<Node> $schemas */
    public function __construct(
        private array $schemas,
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
        $mark = $run->mark();
        $valid = false;
        foreach ($this->schemas as $index => $schema) {
            $kp = $run->path($keywordLocation, 'anyOf', $index);
            $matches = $schema->evaluate($value, $run, $instanceLocation, $kp, $annotations);
            if (!$matches) {
                continue;
            }
            $valid = true;
        }
        if ($valid) {
            $run->rollBack($mark);
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/anyOf',
            $this->location,
            'The value must match at least one of the "anyOf" schemas.',
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return $this->schemas;
    }
}
