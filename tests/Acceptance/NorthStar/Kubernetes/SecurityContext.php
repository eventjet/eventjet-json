<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final readonly class SecurityContext
{
    public function __construct(
        public bool $allowPrivilegeEscalation,
        public bool $readOnlyRootFilesystem,
        public bool $runAsNonRoot,
        public int $runAsUser,
        public Capabilities $capabilities,
    ) {}
}
