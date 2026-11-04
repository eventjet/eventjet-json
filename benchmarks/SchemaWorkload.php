<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use Eventjet\Json\Schema\Validator;
use RuntimeException;

/** @internal */
final readonly class SchemaWorkload
{
    public function __construct(
        public Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema,
        public mixed $value,
    ) {}

    /** @throws RuntimeException */
    public function compile(): Validator
    {
        $validator = Validator::compile($this->schema);
        if ($validator instanceof SchemaError) {
            throw new RuntimeException('The benchmark schema does not compile.', previous: $validator);
        }
        return $validator;
    }

    /** @throws RuntimeException */
    public function verify(Validator $validator): void
    {
        $error = $validator->validateValue($this->value);
        if ($error !== null) {
            throw new RuntimeException('The benchmark value does not match its schema.', previous: $error);
        }
    }
}
