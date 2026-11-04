<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function array_key_exists;
use function sprintf;

/**
 * Which groups of keywords a schema resource uses, as the `$vocabulary` of its meta-schema says.
 *
 * @internal
 */
final readonly class Vocabulary
{
    public function __construct(
        public bool $applicator = true,
        public bool $unevaluated = true,
        public bool $validation = true,
        public bool $formatAssertion = false,
    ) {}

    public static function all(bool $formatAssertion): self
    {
        return new self(formatAssertion: $formatAssertion);
    }

    /** @throws SchemaError If a vocabulary that the meta-schema requires is unknown. */
    public static function fromMetaSchema(stdClass $vocabularies, Draft $draft, bool $assertFormats, string $uri): self
    {
        $prefix = $draft === Draft::Draft202012
            ? 'https://json-schema.org/draft/2020-12/vocab/'
            : 'https://json-schema.org/draft/2019-09/vocab/';
        $known = $draft === Draft::Draft202012
            ? [
                'core',
                'applicator',
                'unevaluated',
                'validation',
                'meta-data',
                'format-annotation',
                'format-assertion',
                'content',
            ]
            : ['core', 'applicator', 'validation', 'meta-data', 'format', 'content'];
        $active = [];
        /** @var mixed $required */
        foreach ((array) $vocabularies as $vocabulary => $required) {
            $name = null;
            foreach ($known as $candidate) {
                if ((string) $vocabulary !== $prefix . $candidate) {
                    continue;
                }

                $name = $candidate;
            }
            if ($name === null) {
                if ($required === true) {
                    throw new SchemaError(sprintf(
                        'The meta-schema %s requires the unknown vocabulary %s.',
                        $uri,
                        (string) $vocabulary,
                    ));
                }
                continue;
            }
            $active[$name] = true;
        }
        $applicator = array_key_exists('applicator', $active);
        return new self(
            applicator: $applicator,
            unevaluated: $draft === Draft::Draft202012 ? array_key_exists('unevaluated', $active) : $applicator,
            validation: array_key_exists('validation', $active),
            formatAssertion: $assertFormats || array_key_exists('format-assertion', $active),
        );
    }
}
