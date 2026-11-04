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

use function array_key_exists;

/** @internal */
final readonly class UnevaluatedPropertiesKeyword implements Keyword
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
        if (!$value instanceof stdClass || $annotations === null) {
            return true;
        }
        $valid = true;
        /** @var mixed $member */
        foreach ((array) $value as $name => $member) {
            $name = (string) $name;
            if (array_key_exists($name, $annotations->properties)) {
                continue;
            }
            $ip = $run->collect ? $instanceLocation . '/' . Uri::escapePointerToken($name) : '';
            $matches = $this->schema->evaluate($member, $run, $ip, $keywordLocation . '/unevaluatedProperties', null);
            if (!$matches) {
                $valid = false;
                if (!$run->collect) {
                    return false;
                }
            }
            $annotations->properties[$name] = true;
        }
        return $valid;
    }
}
