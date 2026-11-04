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
use JsonException;
use Override;
use RuntimeException;

use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class SchemaWorkload implements RequestWorkload
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

    /**
     * Compiles the schema and validates the value with it, which is what the first validation in a request costs.
     *
     * @throws RuntimeException
     */
    #[Override]
    public function run(): Validator
    {
        $validator = $this->compile();
        $validator->validateValue($this->value);
        return $validator;
    }

    /** @throws RuntimeException */
    #[Override]
    public function verify(mixed $result): void
    {
        if (!$result instanceof Validator) {
            throw new RuntimeException('The benchmark did not compile a validator.');
        }
        $error = $result->validateValue($this->value);
        if ($error !== null) {
            throw new RuntimeException('The benchmark value does not match its schema.', previous: $error);
        }
    }

    /** @throws JsonException */
    #[Override]
    public function inputBytes(): int
    {
        return strlen(json_encode($this->value, JSON_THROW_ON_ERROR));
    }
}
