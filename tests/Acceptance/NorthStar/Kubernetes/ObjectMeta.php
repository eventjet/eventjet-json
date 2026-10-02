<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use ArrayObject;

/** @api Consumed dynamically by NorthStarTest. */
final class ObjectMeta
{
    public string $name = '';

    public string $namespace = '';

    public string $uid = '';

    public string $resourceVersion = '';

    public int $generation = 0;

    public string $creationTimestamp = '';

    /** @var ArrayObject<string, string> */
    public ArrayObject $labels;

    /** @var ArrayObject<string, string> */
    public ArrayObject $annotations;

    /** @var list<string> */
    public array $finalizers = [];

    public function __construct()
    {
        /** @var ArrayObject<string, string> $labels */
        $labels = new ArrayObject();
        /** @var ArrayObject<string, string> $annotations */
        $annotations = new ArrayObject();
        $this->labels = $labels;
        $this->annotations = $annotations;
    }
}
