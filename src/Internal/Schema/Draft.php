<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use stdClass;

use function is_string;

/** @internal */
enum Draft: int
{
    case Draft4 = 4;
    case Draft6 = 6;
    case Draft7 = 7;
    case Draft201909 = 2019;
    case Draft202012 = 2020;

    public static function of(Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema): self
    {
        return match (true) {
            $schema instanceof Draft4 => self::Draft4,
            $schema instanceof Draft6 => self::Draft6,
            $schema instanceof Draft7 => self::Draft7,
            $schema instanceof Draft201909 => self::Draft201909,
            $schema instanceof Draft202012 => self::Draft202012,
        };
    }

    /** Returns the draft whose meta-schema has the given URI, with or without an empty fragment. */
    public static function ofMetaSchema(string $uri): self|null
    {
        $uri = Uri::withoutEmptyFragment($uri);
        foreach (self::cases() as $draft) {
            if ($draft->metaSchema() === $uri) {
                return $draft;
            }
        }
        return null;
    }

    /**
     * The URI of the draft's meta-schema.
     *
     * @mutation-free
     */
    private function metaSchema(): string
    {
        return match ($this) {
            self::Draft4 => 'http://json-schema.org/draft-04/schema',
            self::Draft6 => 'http://json-schema.org/draft-06/schema',
            self::Draft7 => 'http://json-schema.org/draft-07/schema',
            self::Draft201909 => 'https://json-schema.org/draft/2019-09/schema',
            self::Draft202012 => 'https://json-schema.org/draft/2020-12/schema',
        };
    }

    /** @return class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> */
    public function className(): string
    {
        return match ($this) {
            self::Draft4 => Draft4::class,
            self::Draft6 => Draft6::class,
            self::Draft7 => Draft7::class,
            self::Draft201909 => Draft201909::class,
            self::Draft202012 => Draft202012::class,
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::Draft4 => 'Draft 4',
            self::Draft6 => 'Draft 6',
            self::Draft7 => 'Draft 7',
            self::Draft201909 => 'Draft 2019-09',
            self::Draft202012 => 'Draft 2020-12',
        };
    }

    /** Returns the `$id` of a schema, which Draft 4 calls `id`, or null if it has none. */
    public function id(stdClass $schema): string|null
    {
        /** @var mixed $id */
        $id = ((array) $schema)[$this === self::Draft4 ? 'id' : '$id'] ?? null;
        return is_string($id) ? $id : null;
    }

    /** @mutation-free */
    public function atLeast(self $draft): bool
    {
        return $this->value >= $draft->value;
    }
}
