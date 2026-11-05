<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;
use stdClass;

use function count;
use function is_array;
use function is_string;
use function preg_match_all;
use function sprintf;
use function strlen;

/**
 * A minimum or maximum count: of code points for strings, of items for arrays, or of members for objects.
 *
 * @internal
 */
final readonly class CountLimitKeyword implements Keyword
{
    /** @param 'string'|'array'|'object' $type */
    public function __construct(
        private string $name,
        private string $type,
        private int|float $limit,
        private bool $maximum,
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
        $count = match ($this->type) {
            'string' => is_string($value) ? strlen($value) - (int) preg_match_all('/[\x80-\xBF]/', $value) : null,
            'array' => is_array($value) ? count($value) : null,
            'object' => $value instanceof stdClass ? count((array) $value) : null,
        };
        if ($count === null || ($this->maximum ? $count <= $this->limit : $count >= $this->limit)) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/' . $this->name,
            $this->location,
            sprintf(
                'The value must have %s %s %s, but it has %d.',
                $this->maximum ? 'at most' : 'at least',
                (string) $this->limit,
                match ($this->type) {
                    'string' => 'characters',
                    'array' => 'items',
                    'object' => 'members',
                },
                $count,
            ),
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
