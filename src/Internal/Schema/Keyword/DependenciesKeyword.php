<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Eventjet\Json\Internal\Schema\Uri;
use Override;
use stdClass;

use function is_array;
use function property_exists;
use function sprintf;

/**
 * `dependencies`, `dependentRequired` and `dependentSchemas`: what a value needs if it has a member.
 *
 * @internal
 */
final readonly class DependenciesKeyword implements Keyword
{
    /** @param array<array-key, Node|list<string>> $dependencies */
    public function __construct(
        private string $name,
        private array $dependencies,
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
        foreach ($this->dependencies as $member => $dependency) {
            $member = (string) $member;
            if (!property_exists($value, $member)) {
                continue;
            }
            $location = $run->collect
                ? $keywordLocation . '/' . $this->name . '/' . Uri::escapePointerToken($member)
                : '';
            if (is_array($dependency)) {
                foreach ($dependency as $required) {
                    if (property_exists($value, $required)) {
                        continue;
                    }

                    $valid = $run->fail(
                        $instanceLocation,
                        $location,
                        $this->location,
                        sprintf('The member "%s" is required when "%s" is present.', $required, $member),
                    );
                    if (!$run->collect) {
                        return false;
                    }
                }
                continue;
            }
            $matches = $dependency->evaluate($value, $run, $instanceLocation, $location, $annotations);
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
}
