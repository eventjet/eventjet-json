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
final class Vocabularies
{
    /**
     * @var array<string, array{stdClass|bool, Draft}> The root and the draft of each document, by the URI that it was
     *     loaded from, so that a meta-schema can name itself or a meta-schema that names it.
     */
    private array $documents = [];

    public function __construct(
        private readonly Registry $registry,
        private readonly bool $assertFormats,
    ) {}

    /** @throws SchemaError */
    public function ofDocument(stdClass|bool $root, Draft $draft, string $uri): Vocabulary
    {
        $this->documents[$uri] = [$root, $draft];
        return $this->of($root, $draft, null);
    }

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
        [$metaRoot, $metaDraft] = $this->metaSchema(Uri::withoutFragment($metaSchema));
        if ($metaDraft !== $draft) {
            throw self::draftMismatch($metaSchema, $metaDraft, $draft);
        }
        /** @var mixed $vocabularies */
        $vocabularies = $metaRoot instanceof stdClass ? ((array) $metaRoot)['$vocabulary'] ?? null : null;
        if (!$vocabularies instanceof stdClass || !$draft->atLeast(Draft::Draft201909)) {
            return Vocabulary::all($this->assertFormats);
        }
        return Vocabulary::fromMetaSchema($vocabularies, $draft, $this->assertFormats, $metaSchema);
    }

    /**
     * @return array{stdClass|bool, Draft}
     * @throws SchemaError
     */
    private function metaSchema(string $uri): array
    {
        $document = $this->documents[$uri] ?? null;
        if ($document !== null) {
            return $document;
        }
        $resource = $this->registry->resource($uri);
        return [$resource->root, $resource->draft];
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
