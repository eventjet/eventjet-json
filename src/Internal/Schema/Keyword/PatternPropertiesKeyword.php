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

use function preg_match;

/** @internal */
final readonly class PatternPropertiesKeyword implements Keyword
{
    /** @param list<array{non-empty-string, string, Node}> $patterns The PCRE pattern, the original pattern, and the schema. */
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
            foreach ($this->patterns as [$pcre, $pattern, $schema]) {
                $matches = preg_match($pcre, $name) === 1;
                if (!$matches) {
                    continue;
                }
                if ($annotations !== null) {
                    $annotations->properties[$name] = true;
                }
                $ip = $run->collect ? $instanceLocation . '/' . Uri::escapePointerToken($name) : '';
                $kp = $run->collect ? $keywordLocation . '/patternProperties/' . Uri::escapePointerToken($pattern) : '';
                $matches = $schema->evaluate($member, $run, $ip, $kp, null);
                if ($matches) {
                    continue;
                }
                $valid = false;
                if (!$run->collect) {
                    return false;
                }
            }
        }
        return $valid;
    }
}
