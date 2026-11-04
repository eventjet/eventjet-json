<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;
use stdClass;

/** @internal */
final readonly class PropertyNamesKeyword implements Keyword
{
    public function __construct(
        private Node $schema,
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
        foreach ((array) $value as $name => $_) {
            $name = (string) $name;
            $ip = $run->path($instanceLocation, $name);
            $matches = $this->schema->evaluate($name, $run, $ip, $keywordLocation . '/propertyNames', null);
            if ($matches) {
                continue;
            }
            $valid = false;
            if (!$run->collect) {
                return false;
            }
        }
        return $valid;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
