<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use function preg_last_error_msg;
use function preg_match;
use function sprintf;

/**
 * A regex of the schema, translated to PCRE.
 *
 * @internal
 */
final readonly class SchemaRegex
{
    /** @param non-empty-string $pcre */
    public function __construct(
        private string $pcre,
        public string $pattern,
        public string $location,
    ) {}

    /**
     * Returns whether the regex matches the subject. If PCRE gives up, for example at its backtrack limit, the run
     * records why, because the result of the whole validation is then unknown.
     */
    public function matches(string $subject, Run $run): bool
    {
        $result = preg_match($this->pcre, $subject);
        if ($result === false) {
            $run->regexLimit = sprintf(
                'PCRE gave up matching the pattern %s at %s: %s.',
                $this->pattern,
                $this->location,
                preg_last_error_msg(),
            );
        }
        return $result === 1;
    }
}
