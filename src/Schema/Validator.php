<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Registry;
use Eventjet\Json\Internal\Schema\Run;
use JsonException;
use Throwable;

use function assert;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * A compiled JSON Schema, which checks values against the schema. Compile a schema once and reuse the validator for
 * any number of values.
 *
 * @api
 */
final readonly class Validator
{
    private function __construct(
        private Node $root,
    ) {}

    /**
     * Compiles a schema. The class of the schema object decides which draft's rules apply.
     *
     * Only the names of the parameters after the schema are stable, not their order, so pass them by name.
     *
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     *     Returns the document with the given URI, for each reference and `$schema` that the schema and the
     *     documents it loads point to, or null if there is none. It is called only while compiling.
     * @param bool $assertFormats Whether `format` checks values, instead of being an annotation only.
     */
    public static function compile(
        Draft4|Draft6|Draft7|Draft201909|Draft202012|bool $schema,
        Closure|null $loader = null,
        bool $assertFormats = false,
    ): self|SchemaError {
        try {
            $registry = new Registry($loader, $assertFormats);
            $resource = $registry->addSchema($schema, '');
            $compiler = new Compiler($registry);
            $root = $compiler->compile($resource->root, $registry->rootLocation($resource));
            $compiler->checkLoops();
        } catch (SchemaError $error) {
            return $error;
        }
        return new self($root);
    }

    /** Returns null if the value matches the schema, or why it doesn't. */
    public function validateValue(mixed $value): ValidationError|null
    {
        /** @var mixed $value */
        $value = self::normalize($value);
        if ($value instanceof ValidationError) {
            return $value;
        }
        $run = new Run();
        $valid = $this->root->evaluate($value, $run, '', '', null);
        if ($valid) {
            return null;
        }
        assert($run->failures !== [], description: 'Every keyword that fails records why.');
        return ValidationError::invalidValue($run->failures);
    }

    /** Returns whether the value matches the schema. */
    public function isValid(mixed $value): bool
    {
        return $this->validateValue($value) === null;
    }

    /** Reads a value the way json_encode() writes it. */
    private static function normalize(mixed $value): mixed
    {
        try {
            return json_decode(self::encode($value), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ValidationError::unsupportedValue($exception->getMessage());
        } catch (Throwable $exception) {
            return ValidationError::unsupportedValue('jsonSerialize() threw: ' . $exception->getMessage());
        }
    }

    /**
     * @throws JsonException
     * @throws Throwable What a jsonSerialize() method in the value throws.
     */
    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
