<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

/** @api Consumed dynamically by acceptance tests. */
abstract class ContainerRuntime
{
    public string $name = '';

    public string $image = '';

    public ImagePullPolicy|null $imagePullPolicy = null;

    /** @var list<string> */
    public array $command = [];

    /** @var list<string> */
    public array $args = [];
}
