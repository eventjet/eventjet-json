<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Prototype;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\CollectionItemValueConverter;
use Eventjet\Json\Internal\CollectionUnionType;
use Eventjet\Json\Internal\CollectionValueConverter;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\ListType;
use Eventjet\Json\Internal\MapType;
use Eventjet\Json\Internal\NestedCollectionType;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\TupleType;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use stdClass;
use Throwable;

/** PROTOTYPE: offsets refer to the original input; no subtree string copies. */
final readonly class Span
{
    public function __construct(
        public int $start,
        public int $end,
        public string $kind,
    ) {}
}

/**
 * PROTOTYPE. Native validation preserves the syntax-before-construction boundary.
 * The custom parser constructs classes/collections directly, without a generic tree.
 * Modes are experiments, not a new public API; see README.md for the matrix.
 */
final class DirectParser
{
    private static array $plans = [];
    private static array $compiled = [];
    private static array $chunkPatterns = [];
    private int $position = 0;
    private array $ends = [];

    public function __construct(
        private string $json,
        private string $mode = 'hybrid',
        private int $chunkSize = 128,
        private bool $lazyPaths = false,
    ) {}

    public static function decode(
        string $json,
        string|JsonType $type,
        string $mode = 'hybrid',
        bool $syntaxValidated = false,
    ): mixed {
        if ($mode === 'fused-batch') {
            try {
                $result = self::fusedBatch($json, $type);
            } catch (Throwable) {
                $result = false;
            }
            if ($result !== false) {
                return $result;
            }
            $mode = 'extreme';
        }
        if ($mode === 'extreme') {
            if (is_string($type)) {
                $result = self::fused($json, $type, false);
                if ($result !== false) {
                    return $result;
                }
                $result = self::fused($json, $type, true, true);
                if ($result !== false) {
                    return $result;
                }
                $result = self::fused($json, $type, true);
                if ($result !== false) {
                    return $result;
                }
            }
            if (!str_contains($json, "\n") && !str_contains($json, ': ')) {
                $result = GraphParser::decode($json, $type, self::plan(...), 'graph-compact-inline-bulk-loop');
                if ($result !== false) {
                    return $result;
                }
            }
            $result = GraphParser::decode($json, $type, self::plan(...), 'graph-flex-inline-direct-bulk');
            if ($result !== false) {
                return $result;
            }
            $mode = 'columns-8192';
        }
        if (in_array($mode, ['fused', 'projection', 'projection-ascii'], true) && is_string($type)) {
            $result = self::fused($json, $type, $mode !== 'fused', $mode === 'projection-ascii');
            if ($result !== false) {
                return $result;
            }
            $mode = 'columns-8192';
        }
        if (in_array($mode, ['fused', 'projection', 'projection-ascii'], true)) {
            $mode = 'columns-8192';
        }
        if (str_starts_with($mode, 'graph')) {
            $result = GraphParser::decode($json, $type, self::plan(...), $mode);
            if ($result !== false) {
                return $result;
            }
            $mode = 'window-8192';
        }
        $regexValidation = str_starts_with($mode, 'validate-');
        if ($regexValidation) {
            $mode = substr($mode, 9);
        }
        $indexed = str_starts_with($mode, 'indexed-');
        if ($indexed) {
            $mode = substr($mode, 8);
        }
        $lazyPaths = str_starts_with($mode, 'lazy-');
        if ($lazyPaths) {
            $mode = substr($mode, 5);
        }
        if (!$syntaxValidated && !($regexValidation ? RegexValidator::validate($json) : json_validate($json))) {
            // Native parsing can report an invalid property name before a later
            // syntax error. Use it on the failure path to preserve that precedence.
            return Json::decode($json, $type);
        }
        if (str_contains($json, '\\u0000') && self::invalidPropertyName($json)) {
            return Json::decode($json, $type);
        }
        $chunkSize = 128;
        if (
            str_starts_with($mode, 'chunks-')
            || str_starts_with($mode, 'packed-')
            || str_starts_with($mode, 'window-')
            || str_starts_with($mode, 'columns-')
        ) {
            [$mode, $size] = explode('-', $mode, 2);
            $chunkSize = max(1, min(in_array($mode, ['window', 'columns'], true) ? 262144 : 4096, (int) $size));
        }
        $parser = new self($json, $mode, $chunkSize, $lazyPaths);
        if ($indexed) {
            $parser->indexEnds();
        }
        $parser->white();
        $kind = $json[$parser->position];
        if (is_string($type)) {
            if ($kind !== '{') {
                return DecodeError::unexpectedRootValue(json_decode($json));
            }
            return $parser->object($type, '');
        }
        $item = $type->collectionItem();
        $expected = $item instanceof NestedCollectionType && $item->collection instanceof MapType ? '{' : '[';
        if ($kind !== $expected) {
            return DecodeError::unexpectedRootValue(json_decode($json), $expected === '{' ? 'object' : 'array');
        }
        // Root collection declaration errors retain the existing decoder's ordering.
        $error = Json::validateType($type);
        if ($error !== null) {
            return Json::decode($json, $type);
        }
        return $parser->item($type->itemClass(), '', $item);
    }

    private static function fused(string $json, string $class, bool $projection, bool $ascii = false): object|false
    {
        if (!$projection && strlen($json) > 32768) {
            return false;
        }
        if ($projection && str_contains($json, '\\u0000') && self::invalidPropertyName($json)) {
            return false;
        }
        try {
            if (!isset(self::$compiled[$class])) {
                if (!json_validate($json)) {
                    return false;
                }
                $plan = self::plan($class);
                self::$compiled[$class] = $plan instanceof DecodeError
                    ? false
                    : self::compile($class, $plan[0], $plan[1]);
            }
            $compiled = self::$compiled[$class];
            if ($compiled === false || $projection && $compiled[10] === false) {
                return false;
            }
            $pattern = $compiled[$projection ? ($ascii ? 12 : 10) : 9];
            $matched = preg_match($pattern, $json, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matched === false && preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
                $limit = ini_get('pcre.backtrack_limit');
                try {
                    ini_set('pcre.backtrack_limit', (string) max((int) $limit, strlen($json) * 32));
                    $matched = preg_match($pattern, $json, $matches, PREG_UNMATCHED_AS_NULL);
                } finally {
                    ini_set('pcre.backtrack_limit', $limit);
                }
            }
            if ($matched !== 1) {
                return false;
            }
            // Projection's ignored values have a statically bounded depth of 3;
            // every key is unescaped. No native validation pass is necessary.
            json_validate('null');
            return $compiled[$projection ? 1 : 5]($matches, false);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    private static function fusedBatch(string $json, string|JsonType $type): mixed
    {
        $parent = null;
        $name = null;
        if (is_string($type)) {
            $plan = self::plan($type);
            if ($plan instanceof DecodeError || count($plan[0]) !== 1 || $plan[1] !== []) {
                return false;
            }
            $name = array_key_first($plan[0]);
            $collection = $plan[0][$name]['type'];
            $parent = $type;
        } else {
            $item = $type->collectionItem();
            $collection = $item instanceof NestedCollectionType ? $item->collection : null;
        }
        if (
            !$collection instanceof ListType
            || !is_string($collection->itemType)
            || !class_exists($collection->itemType)
            || enum_exists($collection->itemType)
        ) {
            return false;
        }
        $class = $collection->itemType;
        $plan = self::plan($class);
        if ($plan instanceof DecodeError) {
            return false;
        }
        $compiled = self::$compiled[$class] ??= self::compile($class, $plan[0], $plan[1]);
        if ($compiled === false || $compiled[11] === false) {
            return false;
        }
        $white = '[\\x20\\x09\\x0a\\x0d]*+';
        $prefix =
            '~\\A'
            . $white
            . ($parent === null ? '' : '\\{' . $white . preg_quote(json_encode($name), '~') . $white . ':' . $white)
            . '\\['
            . $white
            . '~';
        if (preg_match($prefix, $json, $match) !== 1) {
            return false;
        }
        $start = strlen($match[0]);
        $count = preg_match_all($compiled[11], $json, $columns, PREG_PATTERN_ORDER | PREG_UNMATCHED_AS_NULL, $start);
        if ($count === false || $count === 0 && $collection->nonEmpty) {
            return false;
        }
        $end = $start;
        foreach ($columns[0] as $row) {
            $end += strlen($row);
        }
        if ($count > 0 && str_ends_with(rtrim($columns[0][$count - 1]), ',')) {
            return false;
        }
        $suffix = '~\\G\\]' . $white . ($parent === null ? '' : '\\}' . $white) . '\\z~';
        if (preg_match($suffix, $json, $match, 0, $end) !== 1) {
            return false;
        }
        // Complete syntax and scalar types are proven before the first target
        // constructor. Captures cost more memory, but eliminate the second scan.
        json_validate('null');
        $out = [];
        $index = 0;
        try {
            if (!$compiled[8]($columns, $out, $start, $index)) {
                throw new \LogicException('Validated scalar batch rejected by its factory.');
            }
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
        if ($parent === null) {
            return $out;
        }
        try {
            return new $parent($out);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($parent, $error);
        }
    }

    private static function invalidPropertyName(string $json): bool
    {
        $offset = 0;
        while (
            preg_match('~"((?:[^"\\\\]++|\\\\.)*+)"[ \t\r\n]*+(:)?~s', $json, $matches, PREG_OFFSET_CAPTURE, $offset)
            === 1
        ) {
            if (isset($matches[2]) && str_starts_with($matches[1][0], '\\u0000')) {
                return true;
            }
            $offset = $matches[0][1] + strlen($matches[0][0]);
        }
        return false;
    }

    private static function plan(string $class): array|DecodeError
    {
        if (isset(self::$plans[$class])) {
            return self::$plans[$class];
        }
        $reflection = new ReflectionClass($class);
        $error = RootTypeValidator::validate($reflection);
        if ($error !== null) {
            return $error;
        }
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $meta = new ConstructorParameter($parameter, $reflection);
            $collection = $meta->resolveType($class);
            if ($collection instanceof DecodeError) {
                return $collection;
            }
            $collection = $collection === false ? null : $collection;
            $arguments[$meta->name] = [
                'validator' => ConstructorValueValidator::forParameter($meta, [$meta->name => true]),
                'converter' => new FieldValueConverter($parameter, $collection),
                'type' => self::directType($parameter, $collection),
                'kind' => $meta->type instanceof ReflectionNamedType && $meta->builtin
                    ? $meta->type->getName()
                    : 'convert',
                'nullable' => $parameter->allowsNull(),
                'enumCases' => self::enumCases($parameter),
                'scalarNames' => $parameter->getType() instanceof ReflectionUnionType
                    ? array_map(static fn($type) => $type->getName(), $parameter->getType()->getTypes())
                    : null,
            ];
        }
        $properties = PublicProperties::resolve($reflection);
        if ($properties instanceof DecodeError) {
            return $properties;
        }
        foreach ($properties as &$property) {
            $collection = FieldTypeResolver::resolve($class, $property['property']);
            $property['type'] = self::directType($property['property'], $collection);
            $property['kind'] = $property['builtinType']?->getName() ?? 'convert';
            $property['nullable'] = $property['property']->getType()?->allowsNull() ?? false;
            $property['enumCases'] = self::enumCases($property['property']);
            $property['scalarNames'] = $property['property']->getType() instanceof ReflectionUnionType
                ? array_map(static fn($type) => $type->getName(), $property['property']->getType()->getTypes())
                : null;
        }
        unset($property);
        return self::$plans[$class] = [$arguments, $properties, $arguments + $properties, $reflection->getName()];
    }

    private static function directType($field, mixed $collection): mixed
    {
        if ($collection !== null) {
            return $collection;
        }
        $type = $field->getType();
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (!$member instanceof ReflectionNamedType || $member->isBuiltin()) {
                    continue;
                }
                $name = FieldTypeNameResolver::resolve($field, $member);
                if (!enum_exists($name) && class_exists($name)) {
                    return $name;
                }
            }
        }
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $name = FieldTypeNameResolver::resolve($field, $type);
            return enum_exists($name) ? null : $name;
        }
        // Enum and scalar unions reuse the existing conversion rules.
        return null;
    }

    private static function enumCases($field): array|null
    {
        $type = $field->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }
        $name = FieldTypeNameResolver::resolve($field, $type);
        if (!is_subclass_of($name, \BackedEnum::class)) {
            return null;
        }
        $cases = [];
        foreach ($name::cases() as $case) {
            $cases[get_debug_type($case->value)][$case->value] = $case;
        }
        return $cases;
    }

    private function object(string $class, string|Breadcrumb $path, int|null $objectEnd = null): object
    {
        $start = $this->position;
        try {
            $plan = self::plan($class);
            if ($plan instanceof DecodeError) {
                // No constructor has run for this object: reuse declaration/error contracts.
                $this->skip();
                return ObjectHydrator::hydrate(
                    $class,
                    json_decode(substr($this->json, $start, $this->position - $start)),
                    (string) $path,
                );
            }
            [$arguments, $properties, $known, $displayClass] = $plan;
            if (
                in_array($this->mode, ['compiled', 'regex', 'ordered', 'chunks', 'packed', 'window', 'columns'], true)
                && (($objectEnd ?? strlen($this->json)) - $start) <= 32768
            ) {
                $compiled = self::$compiled[$class] ??= self::compile($class, $arguments, $properties);
                $numbered = in_array($this->mode, ['packed', 'window', 'columns'], true);
                $pattern = $compiled === false
                    ? ''
                    : (
                        $numbered
                            ? $compiled[6]
                            : (in_array($this->mode, ['ordered', 'chunks'], true) ? $compiled[2] : $compiled[0])
                    );
                // Limit captures/copies for unusually large scalar objects. Large
                // strings then use the general parser's single substring allocation.
                $source =
                    $compiled !== false && (strlen($this->json) - $start) > 32768
                        ? substr($this->json, $start, 32768)
                        : $this->json;
                $offset = $source === $this->json ? $start : 0;
                $matched =
                    $compiled !== false
                    && preg_match($pattern, $source, $matches, PREG_UNMATCHED_AS_NULL, $offset) === 1;
                if (
                    !$matched
                    && $compiled !== false
                    && in_array($this->mode, ['ordered', 'chunks', 'packed', 'window', 'columns'], true)
                ) {
                    $matched = preg_match($compiled[0], $source, $matches, PREG_UNMATCHED_AS_NULL, $offset) === 1;
                    $numbered = false;
                }
                if ($matched) {
                    $object = $compiled[$numbered ? 5 : 1]($matches, $this->mode === 'compiled');
                    if ($object !== false) {
                        $this->position += strlen($matches[0]);
                        return $object;
                    }
                }
            }
            $values = [];
            ++$this->position;
            $this->white();
            if ($this->json[$this->position] !== '}') {
                do {
                    $name = $this->string();
                    $this->white();
                    ++$this->position; // colon
                    $this->white();
                    if (isset($known[$name])) {
                        $char = $this->json[$this->position];
                        if ($char === '{' || $char === '[') {
                            $begin = $this->position;
                            $this->skip();
                            $values[$name] = new Span($begin, $this->position, $char);
                        } else {
                            $values[$name] = $this->scalar();
                        }
                    } else {
                        $this->skip();
                    }
                    $this->white();
                    $separator = $this->json[$this->position++];
                    $this->white();
                } while ($separator === ',');
            } else {
                ++$this->position;
            }
            $end = $this->position;
            // Validate all named constructor values before converting any child.
            foreach ($arguments as $name => $field) {
                if (!array_key_exists($name, $values) || $field['validator'] === null) {
                    continue;
                }
                $value = $values[$name];
                $raw = $value instanceof Span ? ($value->kind === '{' ? new stdClass() : []) : $value;
                $error = $field['validator']->validate($displayClass, $name, $raw, '');
                if ($error !== null) {
                    return $field['validator']->validate($displayClass, $name, $raw, (string) $path);
                }
            }
            $converted = [];
            foreach ($arguments as $name => $field) {
                if (!array_key_exists($name, $values)) {
                    continue;
                }
                $value = $this->field($displayClass, $this->fieldPath($path, $name), $field, $values[$name]);
                if ($value instanceof DecodeError) {
                    return $value;
                }
                $converted[$name] = $value;
            }
            $assignments = [];
            foreach ($values as $name => $value) {
                if (!isset($properties[$name])) {
                    continue;
                }
                $value = $this->field($displayClass, $this->fieldPath($path, $name), $properties[$name], $value);
                if ($value instanceof DecodeError) {
                    return $value;
                }
                $assignments[] = [$properties[$name]['property'], $value];
            }
            $object = new $class(...$converted);
            foreach ($assignments as [$property, $value]) {
                $property->setValue($object, $value);
            }
            $this->position = $end;
            return $object;
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    private function field(string $class, string|Breadcrumb $path, array $field, mixed $value): mixed
    {
        if ($value instanceof Span) {
            $type = $field['type'];
            if (is_string($type) && $value->kind === '{') {
                $this->position = $value->start;
                return $this->object($type, $path, $value->end);
            }
            if ($type instanceof ListType || $type instanceof MapType || $type instanceof TupleType) {
                $this->position = $value->start;
                return $this->collection($class, $path, $type, $value->end);
            }
            if ($type instanceof CollectionUnionType) {
                $collection = $type->collectionFor($value->kind === '{' ? new stdClass() : []);
                if ($collection !== null) {
                    $this->position = $value->start;
                    return $this->collection($class, $path, $collection, $value->end);
                }
            }
            $value = json_decode(substr($this->json, $value->start, $value->end - $value->start));
        }
        if ($this->lazyPaths && !is_array($value) && !is_object($value)) {
            // Scalar conversion cannot invoke a target constructor. On failure,
            // repeat it with the materialized path for the exact error contract.
            $converted = $field['converter']->convert($class, $value, '');
            return $converted instanceof DecodeError
                ? $field['converter']->convert($class, $value, (string) $path)
                : $converted;
        }
        return $field['converter']->convert($class, $value, (string) $path);
    }

    private function collection(
        string $class,
        string|Breadcrumb $path,
        ListType|MapType|TupleType $type,
        int|null $end = null,
    ): mixed {
        $start = $this->position;
        $map = $type instanceof MapType;
        if ($this->json[$start] !== ($map ? '{' : '[')) {
            return CollectionValueConverter::convert($class, (string) $path, $type, $this->native());
        }
        $item = $type instanceof ListType ? $type->itemType : ($map ? $type->valueType : null);
        // Native bulk scalar conversion is much cheaper than a PHP loop.
        if ($this->mode !== 'pure' && is_string($item) && in_array($item, ['string', 'int', 'float', 'bool'], true)) {
            if ($end !== null) {
                $this->position = $end;
                return CollectionValueConverter::convert(
                    $class,
                    (string) $path,
                    $type,
                    json_decode(substr($this->json, $start, $end - $start)),
                );
            }
            return CollectionValueConverter::convert($class, (string) $path, $type, $this->native());
        }
        // Map key and tuple length checks must precede child constructors.
        if ($map || $type instanceof TupleType) {
            $spans = [];
            ++$this->position;
            $this->white();
            $close = $map ? '}' : ']';
            if ($this->json[$this->position] !== $close) {
                do {
                    $key = $map ? $this->string() : count($spans);
                    if ($map) {
                        $this->white();
                        ++$this->position;
                        $this->white();
                    }
                    $begin = $this->position;
                    $this->skip();
                    $spans[$key] = $begin;
                    $this->white();
                    $separator = $this->json[$this->position++];
                    $this->white();
                } while ($separator === ',');
            } else {
                ++$this->position;
            }
            $end = $this->position;
            $bad = $map
                ? !$type->arrayObject && $spans === [] || count(array_filter(array_keys($spans), 'is_int')) > 0
                : count($spans) < $type->required || count($spans) > count($type->types);
            if ($bad) {
                return CollectionValueConverter::convert(
                    $class,
                    (string) $path,
                    $type,
                    json_decode(substr($this->json, $start, $end - $start)),
                );
            }
            $out = [];
            foreach ($spans as $key => $begin) {
                $this->position = $begin;
                $value = $this->item($class, $this->indexPath($path, $key, $map), $map ? $item : $type->types[$key]);
                if ($value instanceof DecodeError) {
                    return $value;
                }
                $out[$key] = $value;
            }
            $this->position = $end;
            return $map && $type->arrayObject ? new ArrayObject($out) : $out;
        }
        ++$this->position;
        $this->white();
        $out = [];
        if ($this->json[$this->position] === ']') {
            ++$this->position;
            return $type->nonEmpty ? CollectionValueConverter::convert($class, (string) $path, $type, []) : [];
        }
        $index = 0;
        do {
            if (
                in_array($this->mode, ['chunks', 'packed', 'window', 'columns'], true)
                && is_string($item)
                && class_exists($item)
                && !enum_exists($item)
            ) {
                $plan = self::plan($item);
                $compiled = $plan instanceof DecodeError
                    ? false
                    : (self::$compiled[$item] ??= self::compile($item, $plan[0], $plan[1]));
                $chunkPattern = $compiled === false
                    ? ''
                    : (self::$chunkPatterns[$item][$this->chunkSize] ??= str_replace(
                        '{1,128}',
                        '{1,' . $this->chunkSize . '}',
                        $compiled[3],
                    ));
                $columns = $this->mode === 'columns';
                $window = $this->mode === 'window' || $columns;
                $matched = false;
                if ($compiled !== false && $window) {
                    $source = substr($this->json, $this->position, $this->chunkSize);
                    $matched =
                        preg_match_all(
                            $compiled[7],
                            $source,
                            $rows,
                            ($columns ? PREG_PATTERN_ORDER : PREG_SET_ORDER) | PREG_UNMATCHED_AS_NULL,
                        ) > 0;
                } elseif (
                    $compiled !== false
                    && preg_match($chunkPattern, $this->json, $chunk, PREG_OFFSET_CAPTURE, $this->position) === 1
                ) {
                    $source = substr($this->json, $this->position, $chunk[0][1] - $this->position);
                    $rowPattern = $this->mode === 'packed' ? str_replace('\\G', '', $compiled[6]) : $compiled[4];
                    $matched = preg_match_all($rowPattern, $source, $rows, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) > 0;
                }
                if ($matched) {
                    if ($columns) {
                        try {
                            $complete = $compiled[8]($rows, $out, $this->position, $index);
                        } catch (Throwable $error) {
                            return DecodeError::cannotInstantiate($item, $error);
                        }
                        unset($rows, $source);
                        if (!$complete) {
                            $value = $this->object($item, $this->indexPath($path, $index++));
                            if ($value instanceof DecodeError) {
                                return $value;
                            }
                            $out[] = $value;
                            $this->white();
                            if ($this->json[$this->position] === ',') {
                                ++$this->position;
                                $this->white();
                            }
                        }
                        if ($this->json[$this->position] === ']') {
                            ++$this->position;
                            return $out;
                        }
                        $separator = ',';
                        continue;
                    }
                    foreach ($rows as $row) {
                        try {
                            $value = $compiled[$this->mode === 'chunks' ? 1 : 5]($row, false);
                        } catch (Throwable $error) {
                            return DecodeError::cannotInstantiate($item, $error);
                        }
                        if ($value === false) {
                            $value = $this->object($item, $this->indexPath($path, $index));
                            if ($window) {
                                $this->white();
                                // The general parser ends at the object; the window match
                                // also includes its following comma, if present.
                                if ($this->json[$this->position] === ',') {
                                    ++$this->position;
                                    $this->white();
                                }
                            }
                        } else {
                            $this->position += strlen($row[0]);
                        }
                        if ($value instanceof DecodeError) {
                            return $value;
                        }
                        $out[] = $value;
                        ++$index;
                        if (!$window) {
                            $this->white();
                            $separator = $this->json[$this->position++];
                            $this->white();
                        }
                    }
                    if ($window) {
                        $separator = $this->json[$this->position] === ']' ? ']' : ',';
                        if ($separator === ']') {
                            ++$this->position;
                        }
                    }
                    unset($rows, $source, $row, $chunk);
                    if ($separator !== ',') {
                        return $out;
                    }
                    continue;
                }
            }
            $value = $this->item($class, $this->indexPath($path, $index++), $item);
            if ($value instanceof DecodeError) {
                return $value;
            }
            $out[] = $value;
            $this->white();
            $separator = $this->json[$this->position++];
            $this->white();
        } while ($separator === ',');
        return $out;
    }

    private function item(string $class, string|Breadcrumb $path, mixed $type): mixed
    {
        if ($type instanceof NestedCollectionType && $this->json[$this->position] !== 'n') {
            return $this->collection($class, $path, $type->collection);
        }
        if (is_string($type) && !enum_exists($type) && class_exists($type) && $this->json[$this->position] === '{') {
            return $this->object($type, $path);
        }
        if ($type instanceof CollectionUnionType) {
            $char = $this->json[$this->position];
            $collection = $type->collectionFor($char === '{' ? new stdClass() : ($char === '[' ? [] : null));
            if ($collection !== null) {
                return $this->collection($class, $path, $collection);
            }
            if ($char === '{') {
                foreach ($type->names() as $name) {
                    if (!enum_exists($name) && class_exists($name)) {
                        return $this->object($name, $path);
                    }
                }
            }
        }
        $char = $this->json[$this->position];
        $value = $char === '{' || $char === '[' ? $this->native() : $this->scalar();
        if ($this->lazyPaths && !is_array($value) && !is_object($value)) {
            $converted = CollectionItemValueConverter::convert($class, '', $type, $value);
            return $converted instanceof DecodeError
                ? CollectionItemValueConverter::convert($class, (string) $path, $type, $value)
                : $converted;
        }
        return CollectionItemValueConverter::convert($class, (string) $path, $type, $value);
    }

    private function fieldPath(string|Breadcrumb $parent, string $name): string|Breadcrumb
    {
        return $this->lazyPaths
            ? new Breadcrumb($parent, $parent === '' ? $name : '.' . $name)
            : FieldPath::field((string) $parent, $name);
    }

    private function indexPath(string|Breadcrumb $parent, int|string $key, bool $map = false): string|Breadcrumb
    {
        $segment = $map ? FieldPath::key('', $key) : '[' . $key . ']';
        return $this->lazyPaths ? new Breadcrumb($parent, $segment) : $parent . $segment;
    }

    private function native(): mixed
    {
        $start = $this->position;
        $this->skip();
        return json_decode(substr($this->json, $start, $this->position - $start));
    }

    private function white(): void
    {
        $this->position += strspn($this->json, " \t\r\n", $this->position);
    }

    private function scalar(): mixed
    {
        $char = $this->json[$this->position];
        if ($char === '"') {
            return $this->string();
        }
        if ($char === 'n') {
            $this->position += 4;
            return null;
        }
        if ($char === 't') {
            $this->position += 4;
            return true;
        }
        if ($char === 'f') {
            $this->position += 5;
            return false;
        }
        $start = $this->position;
        $this->position += strspn($this->json, '-+0123456789.eE', $start);
        $number = substr($this->json, $start, $this->position - $start);
        return strpbrk($number, '.eE') === false ? $number + 0 : (float) $number;
    }

    private function string(): string
    {
        $start = $this->position++;
        $this->position += strcspn($this->json, "\"\\", $this->position);
        if ($this->json[$this->position] === '"') {
            $out = substr($this->json, $start + 1, $this->position - $start - 1);
            ++$this->position;
            return $this->mode === 'native-strings'
                ? json_decode(substr($this->json, $start, $this->position - $start))
                : $out;
        }
        while ($this->json[$this->position] !== '"') {
            $this->position += 2;
            $this->position += strcspn($this->json, "\"\\", $this->position);
        }
        ++$this->position;
        $token = substr($this->json, $start, $this->position - $start);
        return $this->mode === 'pure' ? self::unescape(substr($token, 1, -1)) : json_decode($token);
    }

    private static function unescape(string $value): string
    {
        return preg_replace_callback(
            '/\\\\(?:u[0-9a-fA-F]{4}(?:\\\\u[0-9a-fA-F]{4})?|["\\\\\/bfnrt])/',
            static function (array $match): string {
                $escape = $match[0];
                if ($escape[1] !== 'u') {
                    return match ($escape[1]) {
                        'b' => "\x08",
                        'f' => "\x0c",
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $escape[1],
                    };
                }
                $code = hexdec(substr($escape, 2, 4));
                if ($code >= 0xD800 && $code <= 0xDBFF) {
                    $code = 0x10000 + (($code - 0xD800) << 10) + hexdec(substr($escape, 8, 4)) - 0xDC00;
                } elseif (strlen($escape) > 6) {
                    return self::utf8($code) . self::unescape(substr($escape, 6));
                }
                return self::utf8($code);
            },
            $value,
        );
    }

    private static function utf8(int $code): string
    {
        return match (true) {
            $code < 0x80 => chr($code),
            $code < 0x800 => chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 63)),
            $code < 0x10000 => chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 63)) . chr(0x80 | ($code & 63)),
            default => chr(0xF0 | ($code >> 18))
                . chr(0x80 | (($code >> 12) & 63))
                . chr(0x80 | (($code >> 6) & 63))
                . chr(0x80 | ($code & 63)),
        };
    }

    private function skip(): void
    {
        if (isset($this->ends[$this->position])) {
            $this->position = $this->ends[$this->position];
            return;
        }
        $char = $this->json[$this->position];
        if (
            in_array($this->mode, ['regex', 'ordered', 'chunks', 'packed', 'window', 'columns'], true)
            && ($char === '[' || $char === '{')
        ) {
            // \K leaves an empty match: report its end offset without copying the subtree.
            $pattern = '~(?(DEFINE)(?<s>"(?:[^"\\\\]++|\\\\.)*+")(?<o>\{(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\})(?<a>\[(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\]))\G(?:(?&o)|(?&a))\K~s';
            $matched = preg_match($pattern, $this->json, $match, PREG_OFFSET_CAPTURE, $this->position);
            if ($matched === false && preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
                // This possessive scanner has linear work. Raise only its work budget,
                // then restore the setting before any target constructor can observe it.
                $limit = ini_get('pcre.backtrack_limit');
                try {
                    ini_set('pcre.backtrack_limit', (string) max((int) $limit, strlen($this->json) * 16));
                    $matched = preg_match($pattern, $this->json, $match, PREG_OFFSET_CAPTURE, $this->position);
                } finally {
                    ini_set('pcre.backtrack_limit', $limit);
                }
            }
            if ($matched === 1) {
                $this->position = $match[0][1];
                return;
            }

            // PCRE resource limits must never change accepted JSON.
        }
        if ($char === '"') {
            ++$this->position;
            do {
                $this->position += strcspn($this->json, "\"\\", $this->position);
                $char = $this->json[$this->position++];
                if ($char === '\\') {
                    ++$this->position;
                }
            } while ($char !== '"');
            return;
        }
        if ($char !== '[' && $char !== '{') {
            $this->position += strcspn($this->json, ",]} \r\n\t", $this->position);
            return;
        }
        $depth = 1;
        ++$this->position;
        do {
            $this->position += strcspn($this->json, '[]{}"', $this->position);
            $char = $this->json[$this->position];
            if ($char === '"') {
                $this->skip();
                continue;
            }
            ++$this->position;
            $depth += $char === '[' || $char === '{' ? 1 : -1;
        } while ($depth !== 0);
    }

    private function indexEnds(): void
    {
        // Bound both indexing time and retained offsets. Large record/ignored
        // trees keep their allocation-free scanner rather than an O(N) index.
        $length = strlen($this->json);
        if ($length > 65536 || (substr_count($this->json, '{') + substr_count($this->json, '[')) > 1024) {
            return;
        }
        $stack = [];
        while ($this->position < $length) {
            $this->position += strcspn($this->json, '[]{}"', $this->position);
            if ($this->position === $length) {
                break;
            }
            $char = $this->json[$this->position];
            if ($char === '"') {
                $this->skip();
            } elseif ($char === '[' || $char === '{') {
                $stack[] = $this->position++;
            } else {
                ++$this->position;
                $this->ends[array_pop($stack)] = $this->position;
            }
        }
        $this->position = 0;
    }

    private static function compile(string $class, array $arguments, array $properties): array|false
    {
        // Resolve aliases to the declared PHP identifier before generating code.
        $class = new ReflectionClass($class)->getName();
        if (str_contains($class, '@')) {
            return false;
        }
        $alternatives = [];
        $strictAlternatives = [];
        $batchEligible = true;
        $expressions = [];
        $conversions = [];
        $index = 0;
        $fields = $arguments + $properties;
        foreach ($fields as $name => $field) {
            if ($field['type'] !== null) {
                return false;
            }
            $kind = $field['kind'];
            $token = match ($kind) {
                'string' => '"(?:[^"\\\\]++|\\\\.)*+"',
                'int', 'float' => '-?(?:0|[1-9][0-9]*+)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?',
                'bool' => '(?:true|false)',
                'true' => 'true',
                'false' => 'false',
                'null' => 'null',
                'convert'
                    => '(?:"(?:[^"\\\\]++|\\\\.)*+"|-?(?:0|[1-9][0-9]*+)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?|true|false|null)',
                default => null,
            };
            if ($token === null) {
                return false;
            }
            if ($field['nullable']) {
                $token = '(?:' . $token . '|null)';
            }
            $capture = 'v' . $index++;
            $alternatives[] = preg_quote(json_encode($name), '~') . '\s*+:\s*+(?<' . $capture . '>' . $token . ')';
            $batchEligible =
                $batchEligible && in_array($kind, ['string', 'int', 'float', 'bool', 'true', 'false', 'null'], true);
            $strictToken = $kind === 'int' ? GraphParser::integerPattern() : GraphParser::strictTokens($token);
            if ($kind === 'int' && $field['nullable']) {
                $strictToken = '(?:null|' . $strictToken . ')';
            }
            $strictAlternatives[] =
                preg_quote(json_encode($name), '~')
                . '[\\x20\\x09\\x0a\\x0d]*+:[\\x20\\x09\\x0a\\x0d]*+('
                . $strictToken
                . ')';
            $value = '$m[' . var_export($capture, true) . ']';
            // json_decode on individual scalar tokens also preserves numeric overflow,
            // negative zero, escaped strings and strict integer validation.
            $native = 'json_decode(' . $value . ')';
            $fast = match ($kind) {
                'string' => '(str_contains(' . $value . ', "\\\\") ? ' . $native . ' : substr(' . $value . ', 1, -1))',
                'int' => '(' . $value . ' + 0)',
                'float' => '(' . $value . ' === "-0" ? 0.0 : (float) ' . $value . ')',
                'bool', 'true', 'false' => '(' . $value . ' === "true")',
                'null' => 'null',
                'convert' => $native,
            };
            if ($field['nullable']) {
                $fast = '(' . $value . ' === "null" ? null : ' . $fast . ')';
            }
            $local = '$a' . ($index - 1);
            $conversions[] =
                'if ('
                . $value
                . ' === null) { return false; } '
                . $local
                . ' = $native ? '
                . $native
                . ' : '
                . $fast
                . ';';
            if ($kind === 'convert') {
                if ($field['enumCases'] !== null) {
                    $nullable = $field['nullable'] ? 'if (' . $local . ' !== null) { ' : '{ ';
                    $conversions[] =
                        $nullable
                        . 'if (!is_string('
                        . $local
                        . ') && !is_int('
                        . $local
                        . ')) { return false; } '
                        . $local
                        . ' = $fields['
                        . var_export($name, true)
                        . ']["enumCases"][get_debug_type('
                        . $local
                        . ')]['
                        . $local
                        . '] ?? false; if ('
                        . $local
                        . ' === false) { return false; } }';
                } else {
                    $conversions[] =
                        $local
                        . ' = $fields['
                        . var_export($name, true)
                        . ']["converter"]->convert($class, '
                        . $local
                        . ', '
                        . var_export($name, true)
                        . '); if ('
                        . $local
                        . ' instanceof \\Eventjet\\Json\\DecodeError) { return false; }';
                }
            }
            $expressions[] = $local;
        }
        if ($alternatives === []) {
            return false;
        }
        $member = '(?:' . implode('|', $alternatives) . ')';
        $pattern = '~\G\{\s*+(?:' . $member . '(?:\s*+,\s*+' . $member . ')*+)?\s*+\}~J';
        if ($properties !== []) {
            // Public property hooks/readonly failures can observe assignment order.
            // Use this fast path only when source order equals declaration order.
            $pattern = '~(?!)~';
        }
        $ordered = '~\G\{\s*+' . implode('\s*+,\s*+', $alternatives) . '\s*+\}~';
        $raw = substr($ordered, 3, -1);
        $boundary = preg_replace('/\(\?<v[0-9]+>/', '(?:', $raw);
        if ($boundary === null) {
            return false;
        }
        $chunk = '~(?(DEFINE)(?<row>' . $boundary . '))\G(?:(?&row)\s*+(?:,\s*+|(?=\]))){1,128}\K~';
        $rows = '~' . $raw . '~';
        // Generated from reflection metadata only. Never interpolate JSON into PHP.
        $body =
            'return static function (array $m, bool $native) use ($class, $fields, $properties): object|false { '
            . implode('', $conversions);
        $index = 0;
        foreach ($fields as $name => $field) {
            $local = '$a' . $index++;
            if ($field['kind'] === 'int') {
                // Overflow or fractional/exponent tokens need the usual error contract.
                $body .= 'if (!is_int(' . $local . ') && ' . $local . ' !== null) { return false; }';
            }
        }
        // Strict direct calls avoid the weak coercions of ReflectionClass::newInstanceArgs.
        $body .=
            '$object = new \\'
            . ltrim($class, '\\')
            . '('
            . implode(',', array_slice($expressions, 0, count($arguments)))
            . ');';
        $index = count($arguments);
        foreach ($properties as $name => $property) {
            $body .= '$properties[' . var_export($name, true) . ']["property"]->setValue($object, $a' . $index++ . ');';
        }
        $body .= 'return $object; };';
        $factory = eval($body);
        $numberedBody = preg_replace_callback(
            '/\$m\[\'v([0-9]+)\'\]/',
            static fn(array $match) => '$m[' . ((int) $match[1] + 1) . ']',
            $body,
        );
        if ($numberedBody === null) {
            return false;
        }
        $numberedFactory = eval($numberedBody);
        $columnBody = substr($numberedBody, strpos($numberedBody, '{') + 1, -3);
        $columnBody = preg_replace('/\$m\[([0-9]+)\]/', '\$columns[$1][$i]', $columnBody);
        if ($columnBody === null) {
            return false;
        }
        $columnBody = str_replace('$native ?', 'false ?', $columnBody);
        $columnBody = str_replace(
            'return $object;',
            '$out[] = $object; $position += strlen($columns[0][$i]); ++$index;',
            $columnBody,
        );
        $columnFactory =
            eval('return static function(array $columns, array &$out, int &$position, int &$index) use ($class, $fields, $properties): bool { $count = count($columns[0]); for ($i = 0; $i < $count; ++$i) {'
                . $columnBody
                . '} return true; };');
        $numbered = preg_replace('/\(\?<v[0-9]+>/', '(', $ordered);
        if ($numbered === null) {
            return false;
        }
        $window = substr($numbered, 0, -1) . '\s*+(?:,\s*+|(?=\]))~';
        $fused =
            GraphParser::strictTokens(str_replace('\\G', '\\A[\\x20\\x09\\x0a\\x0d]*+', substr($numbered, 0, -1)))
            . '[\\x20\\x09\\x0a\\x0d]*+\\z~u';
        $projection = $properties === [] ? GraphParser::projection($alternatives, array_keys($fields)) : false;
        $white = '[\\x20\\x09\\x0a\\x0d]*+';
        $batch = $batchEligible
            ? '~\\G\\{'
            . $white
            . implode($white . ',' . $white, $strictAlternatives)
            . $white
            . '\\}'
            . $white
            . '(?:,'
            . $white
            . '|(?=\\]))~u'
            : false;
        return [
            $pattern,
            $factory,
            $ordered,
            $chunk,
            $rows,
            $numberedFactory,
            $numbered,
            $window,
            $columnFactory,
            $fused,
            $projection,
            $batch,
            $projection === false ? false : GraphParser::asciiPattern($projection),
        ];
    }
}
