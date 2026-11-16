<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Run;
use Eventjet\Json\Internal\Schema\SchemaRegex;
use Override;
use stdClass;

/** @internal */
final readonly class PatternPropertiesKeyword implements Keyword
{
    /** @param list<array{SchemaRegex, Node}> $patterns The regex and the schema of each entry. */
    public function __construct(
        private array $patterns,
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
        /** @var mixed $member */
        foreach ((array) $value as $name => $member) {
            $name = (string) $name;
            foreach ($this->patterns as [$regex, $schema]) {
                $matches = $regex->matches($name, $run);
                if (!$matches) {
                    continue;
                }
                if ($annotations !== null) {
                    $annotations->properties[$name] = true;
                }
                $ip = $run->path($instanceLocation, $name);
                $kp = $run->path($keywordLocation, 'patternProperties', $regex->pattern);
                $matches = $schema->evaluate($member, $run, $ip, $kp, null);
                if ($matches) {
                    continue;
                }
                $valid = false;
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
