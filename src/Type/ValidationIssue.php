<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;
use Stringable;

use function sprintf;

final class ValidationIssue implements Stringable
{
    public function __construct(public readonly string $message, public readonly string $path)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s at %s', $this->message, $this->path);
    }

    public function equals(self $other): bool
    {
        return $this->message === $other->message && $this->path === $other->path;
    }
}
