<?php

declare(strict_types=1);

namespace Eventjet\Json;

interface JsonScalar
{
    public function schema(): Schema;
}
