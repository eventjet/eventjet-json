<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class EnvVarSource
{
    public function __construct(
        public ObjectFieldSelector|null $fieldRef,
        public SecretKeySelector|null $secretKeyRef,
    ) {}
}
