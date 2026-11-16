<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;
use stdClass;

use function property_exists;
use function sprintf;

/** @internal */
final readonly class RequiredKeyword implements Keyword
{
    /** @param list<string> $names */
    public function __construct(
        private array $names,
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
        if (!$value instanceof stdClass) {
            return true;
        }
        $valid = true;
        foreach ($this->names as $name) {
            if (property_exists($value, $name)) {
                continue;
            }

            $valid = $run->fail(
                $instanceLocation,
                $keywordLocation . '/required',
                $this->location,
                sprintf('The member "%s" is required.', $name),
            );
        }
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
