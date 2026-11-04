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
use function preg_match;

/** @internal */
final readonly class AdditionalPropertiesKeyword implements Keyword
{
    /**
     * @param array<string, true> $properties The members that `properties` covers.
     * @param list<non-empty-string> $patterns The PCRE patterns of `patternProperties`.
     */
    public function __construct(
        private Node $schema,
        private array $properties,
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
            if (array_key_exists($name, $this->properties)) {
                continue;
            }
            foreach ($this->patterns as $pcre) {
                $matches = preg_match($pcre, $name) === 1;
                if ($matches) {
                    continue 2;
                }
            }
            if ($annotations !== null) {
                $annotations->properties[$name] = true;
            }
            $ip = $run->path($instanceLocation, $name);
            $matches = $this->schema->evaluate($member, $run, $ip, $keywordLocation . '/additionalProperties', null);
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
