<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;
use JsonException;
use stdClass;

use function array_map;
use function get_object_vars;
use function in_array;
use function json_encode;
use function strpos;
use function substr;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The schema of a case of the official JSON Schema Test Suite, with what the case says about compiling it.
 *
 * @internal
 */
final readonly class OfficialSuiteSchema
{
    /**
     * @param string $json The schema document.
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class The class for documents without
     *     `$schema`.
     * @param array{assertFormats?: true} $options The named arguments for compiling that differ from the defaults.
     * @param array<array-key, string> $externalSchemas The JSON of each external schema by its retrieval URI. They
     *     belong to this case alone, because the suite reuses URIs for different documents.
     */
    public function __construct(
        public string $json,
        public string $class,
        public array $options,
        public array $externalSchemas,
    ) {}

    /**
     * @param object{tags?: list<string>, schema: mixed, externalSchemas?: mixed} $case A case of the suite.
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @throws JsonException
     */
    public static function ofCase(object $case, string $class): self
    {
        /** @var mixed $external */
        $external = $case->externalSchemas ?? null;
        $externalSchemas = $external instanceof stdClass ? get_object_vars($external) : [];
        $assertFormats = in_array('format-assertion', $case->tags ?? [], strict: true);
        return new self(
            self::encode($case->schema),
            $class,
            $assertFormats ? ['assertFormats' => true] : [],
            array_map(self::encode(...), $externalSchemas),
        );
    }

    public function withJson(string $json): self
    {
        return new self($json, $this->class, $this->options, $this->externalSchemas);
    }

    public function withAssertedFormats(): self
    {
        return new self($this->json, $this->class, ['assertFormats' => true], $this->externalSchemas);
    }

    /**
     * Returns a loader for the external schemas, which decodes documents without `$schema` into the class.
     *
     * @return Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null)
     */
    public function loader(): Closure
    {
        $class = $this->class;
        $externalSchemas = $this->externalSchemas;
        $loader = static function (string $uri) use (
            &$loader,
            $class,
            $externalSchemas,
        ): Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null {
            $hash = strpos($uri, needle: '#');
            if ($hash !== false) {
                $uri = substr($uri, offset: 0, length: $hash);
            }
            $json = $externalSchemas[$uri] ?? null;
            if ($json === null) {
                return null;
            }
            /** @var Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null) $loader */
            return JsonSchema::decode($json, $class, loader: $loader);
        };
        return $loader;
    }

    /** @throws JsonException */
    private static function encode(mixed $json): string
    {
        return json_encode(
            $json,
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
