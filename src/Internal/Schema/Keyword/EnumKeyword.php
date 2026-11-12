<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\JsonKey;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function array_key_exists;

/** @internal */
final readonly class EnumKeyword implements Keyword
{
    /** @param array<string, true> $keys */
    public function __construct(
        private array $keys,
        private string $name,
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
        $key = JsonKey::of($value);
        if (array_key_exists($key, $this->keys)) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/' . $this->name,
            $this->location,
            $this->name === 'const'
                ? 'The value must be equal to the constant.'
                : 'The value must be one of the enumerated values.',
        );
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
