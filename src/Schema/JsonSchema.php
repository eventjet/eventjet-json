<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

final class JsonSchema
{
    public static function generate(string $type): Schema
    {
        return (new SchemaGenerator())->generate($type);
    }
}
