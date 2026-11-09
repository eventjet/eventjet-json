<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;

/** @internal */
final readonly class NotKeyword implements Keyword
{
    public function __construct(
        private Node $schema,
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
        $matches = $run->quietly($this->schema, $value, null);
        if (!$matches) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/not',
            $this->location,
            'The value must not match the "not" schema.',
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [$this->schema];
    }
}
