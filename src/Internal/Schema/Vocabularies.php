<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function is_string;
use function sprintf;

/**
 * Finds the vocabulary of a schema resource from the meta-schema that its `$schema` names.
 *
 * @internal
 */
final readonly class Vocabularies
{
    public function __construct(
        private Registry $registry,
        private bool $assertFormats,
    ) {}

    /**
     * @param Resource|null $parent The resource that encloses the schema, if it isn't the root of a document.
     * @throws SchemaError
     */
    public function of(stdClass|bool $schema, Draft $draft, Resource|null $parent): Vocabulary
    {
        /** @var mixed $metaSchema */
        $metaSchema = $schema instanceof stdClass ? ((array) $schema)['$schema'] ?? null : null;
        if (!is_string($metaSchema) || $parent !== null && !$draft->atLeast(Draft::Draft201909)) {
            return $parent->vocabulary ?? Vocabulary::all($this->assertFormats);
        }
        $official = Draft::ofMetaSchema($metaSchema);
        if ($official !== null) {
            if ($official !== $draft) {
                throw self::draftMismatch($metaSchema, $official, $draft);
            }
            return Vocabulary::all($this->assertFormats);
        }
        $meta = $this->registry->resource(Uri::withoutFragment($metaSchema));
        if ($meta->draft !== $draft) {
            throw self::draftMismatch($metaSchema, $meta->draft, $draft);
        }
        /** @var mixed $vocabularies */
        $vocabularies = $meta->root instanceof stdClass ? ((array) $meta->root)['$vocabulary'] ?? null : null;
        if (!$vocabularies instanceof stdClass || !$draft->atLeast(Draft::Draft201909)) {
            return Vocabulary::all($this->assertFormats);
        }
        return Vocabulary::fromMetaSchema($vocabularies, $draft, $this->assertFormats, $metaSchema);
    }

    private static function draftMismatch(string $metaSchema, Draft $named, Draft $draft): SchemaError
    {
        return new SchemaError(sprintf(
            'The $schema %s names %s, but the schema is a %s schema.',
            $metaSchema,
            $named->name(),
            $draft->name(),
        ));
    }
}
