<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\JsonKey;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\EnumKeyword;
use Eventjet\Json\Internal\Schema\Keyword\TypeKeyword;
use Eventjet\Json\Schema\SchemaError;

use function in_array;
use function is_string;
use function sprintf;

/**
 * Compiles `type`, `enum` and `const`, which apply to values of any type.
 *
 * @internal
 */
final class AnyTypeKeywords
{
    private const array TYPES = ['array', 'boolean', 'integer', 'null', 'number', 'object', 'string'];

    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(SchemaObject $schema): array
    {
        $keywords = [];
        if ($schema->has('type')) {
            $keywords[] = new TypeKeyword(self::types($schema), $schema->draft === Draft::Draft4, $schema->at('type'));
        }
        $enum = $schema->array('enum');
        if ($enum !== null) {
            $keys = [];
            /** @var mixed $item */
            foreach ($enum as $item) {
                $keys[JsonKey::of($item)] = true;
            }
            $keywords[] = new EnumKeyword($keys, 'enum', $schema->at('enum'));
        }
        if ($schema->draft->atLeast(Draft::Draft6) && $schema->has('const')) {
            $keywords[] = new EnumKeyword([JsonKey::of($schema->get('const')) => true], 'const', $schema->at('const'));
        }
        return $keywords;
    }

    /**
     * @return list<'array'|'boolean'|'integer'|'null'|'number'|'object'|'string'>
     * @throws SchemaError
     */
    private static function types(SchemaObject $schema): array
    {
        $types = [];
        /** @var mixed $type */
        foreach ($schema->array('type') ?? [$schema->get('type')] as $type) {
            if (!is_string($type) || !in_array($type, self::TYPES, strict: true)) {
                throw new SchemaError(sprintf('The keyword at %s names an unknown type.', $schema->at('type')));
            }
            $types[] = $type;
        }
        return $types;
    }
}
