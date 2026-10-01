<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by NorthStarTest. */
final class ObjectMeta
{
    public string $name = '';

    public string $namespace = '';

    public string $uid = '';

    public string $resourceVersion = '';

    public int $generation = 0;

    public string $creationTimestamp = '';

    /** @var array<string, string> */
    public array $labels = [];

    /** @var array<string, string> */
    public array $annotations = [];

    /** @var list<string> */
    public array $finalizers = [];
}
