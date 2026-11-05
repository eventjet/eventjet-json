<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Override;
use stdClass;

use function array_key_exists;

/** @internal */
final readonly class PropertiesKeyword implements Keyword
{
    /** @param array<array-key, Node> $properties */
    public function __construct(
        private array $properties,
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
        $members = (array) $value;
        $valid = true;
        foreach ($this->properties as $name => $schema) {
            $name = (string) $name;
            if (!array_key_exists($name, $members)) {
                continue;
            }
            if ($annotations !== null) {
                $annotations->properties[$name] = true;
            }
            $matches = $schema->evaluate(
                $members[$name],
                $run,
                $run->path($instanceLocation, $name),
                $run->path($keywordLocation, 'properties', $name),
                null,
            );
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
