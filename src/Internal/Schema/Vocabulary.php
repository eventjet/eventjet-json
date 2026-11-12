<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function in_array;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Which groups of keywords a schema resource uses, as the `$vocabulary` of its meta-schema says.
 *
 * @internal
 */
final readonly class Vocabulary
{
    public function __construct(
        public bool $formatAssertion,
        public bool $applicator = true,
        public bool $unevaluated = true,
        public bool $validation = true,
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
            $vocabulary = (string) $vocabulary;
            $name = str_starts_with($vocabulary, $prefix) ? substr($vocabulary, strlen($prefix)) : null;
            if (!in_array($name, $known, strict: true)) {
                if ($required === true) {
                    throw new SchemaError(sprintf(
                        'The meta-schema %s requires the unknown vocabulary %s.',
                        $uri,
                        $vocabulary,
                    ));
                }
                continue;
            }
            $active[] = $name;
        }
        $applicator = in_array('applicator', $active, strict: true);
        return new self(
            applicator: $applicator,
            unevaluated: $draft === Draft::Draft202012 ? in_array('unevaluated', $active, strict: true) : $applicator,
            validation: in_array('validation', $active, strict: true),
            formatAssertion: $assertFormats || in_array('format-assertion', $active, strict: true),
        );
    }
}
