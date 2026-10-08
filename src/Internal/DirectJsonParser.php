<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\NativeJsonDecoder as Json;
use Eventjet\Json\JsonType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use stdClass;
use Throwable;

/**
 * @internal
 * @mago-expect lint:cyclomatic-complexity JSON and PHP type variants are explicit in this parser/compiler.
 * @mago-expect lint:kan-defect The parser/compiler keeps schema and cursor invariants together.
 * @mago-expect lint:too-many-methods Private token and grammar helpers share the validated cursor/compiler state.
 * @mago-expect lint:halstead Generated PHP and PCRE strings account for most of this measured volume.
 * @mago-expect lint:no-boolean-flag-parameter Internal flags select the measured compact and general parsing strategies.
 * @mago-expect lint:no-isset Non-null metadata and presence checks are on measured parser hot paths.
 * @mago-expect lint:no-nested-ternary Small token/layout selections stay inline in the scanner.
 * @mago-expect lint:no-ini-set A bounded PCRE work-budget retry restores the original setting before user code.
 * @mago-expect lint:no-multi-assignments Cache insertion and returning the same immutable plan are one operation.
 * @mago-expect lint:no-else-clause Mutually exclusive grammar and cursor transitions stay together.
 * @mago-expect lint:literal-named-argument Scanner and code-emitter calls use positional arguments consistently.
 * @mago-expect lint:no-eval Only reflected declarations and fixed emitter strings become PHP; JSON never becomes code.
 * @mago-expect lint:no-redundant-math Adding zero converts validated numeric strings while preserving integer overflow.
 * @mago-expect lint:inline-variable-return Local annotations document the validated-token and emitted-closure boundaries.
 * @mago-expect analysis:side-effects-in-condition Short-circuit validation preserves autoload and failure ordering.
 * @phpstan-type Field array{validator: ConstructorValueValidator|null, converter: FieldValueConverter, property: \ReflectionProperty, type: string|ListType|MapType|TupleType|CollectionUnionType|null, kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames: array<string>|null}
 * @phpstan-type Plan array{array<string, Field>, array<string, Field>, array<string, Field>, class-string}
 * @phpstan-type ScalarField array{kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames?: array<string>|null}
 * @psalm-type Field array{validator: ConstructorValueValidator|null, converter: FieldValueConverter, property: \ReflectionProperty, type: string|ListType|MapType|TupleType|CollectionUnionType|null, kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames: array<string>|null}
 * @psalm-type Plan array{array<string, Field>, array<string, Field>, array<string, Field>, class-string}
 * @psalm-type ScalarField array{kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames?: array<string>|null}
 */
final class DirectJsonParser
{
    /** @var array<class-string, Plan> */
    private static array $plans = [];
    /** @var array<class-string, DirectScalarPlan|false> */
    private static array $compiled = [];
    private int $position = 0;

    private function __construct(
        private string $json,
    ) {}

    /**
     * @param class-string|JsonType<mixed> $type
     * @throws \UnhandledMatchError|\LogicException|\ReflectionException|\JsonException */
    public static function decode(string $json, string|JsonType $type): mixed
    {
        if (!is_string($type) && !$type instanceof ArrayJsonType && !$type instanceof MapJsonType) {
            // An application-defined descriptor owns its decodeValue behavior.
            return self::fallback($json, $type);
        }
        // Native decoding rejects leading-NUL object keys, even in ignored data.
        if (str_contains($json, '\\u0000')) {
            return self::fallback($json, $type);
        }
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
            $result = DirectGraphCompiler::decode($json, $type, self::plan(...), true);
            if ($result !== false) {
                return $result;
            }
        }
        $result = DirectGraphCompiler::decode($json, $type, self::plan(...), false);
        if ($result !== false) {
            return $result;
        }
        if (!json_validate($json)) {
            return self::fallback($json, $type);
        }
        $parser = new self($json);
        $parser->white();
        $kind = $json[$parser->position];
        if (is_string($type)) {
            if ($kind !== '{') {
                return DecodeError::unexpectedRootValue(json_decode($json));
            }
            return $parser->object($type, '');
        }
        $item = $type->collectionItem();
        $expected = $item->collection instanceof MapType ? '{' : '[';
        if ($kind !== $expected) {
            return DecodeError::unexpectedRootValue(json_decode($json), $expected === '{' ? 'object' : 'array');
        }
        // Root collection declaration errors retain the existing decoder's ordering.
        $error = \Eventjet\Json\Json::validateType($type);
        if ($error !== null) {
            return self::fallback($json, $type);
        }
        return $parser->item($type->itemClass(), '', $item);
    }

    /**
     * @param class-string $class
     */
    private static function fused(string $json, string $class, bool $projection, bool $ascii = false): object|false
    {
        if (!$projection && strlen($json) > 32_768) {
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
            if ($compiled === false || $projection && $compiled->projectionPattern === false) {
                return false;
            }
            $pattern = $projection
                ? ($ascii ? $compiled->asciiProjectionPattern : $compiled->projectionPattern)
                : $compiled->fusedPattern;
            if ($pattern === false) {
                return false;
            }
            $matches = [];
            $matched = preg_match($pattern, $json, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matched === false && preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
                $limit = ini_get('pcre.backtrack_limit');
                try {
                    ini_set('pcre.backtrack_limit', (string) max((int) $limit, strlen($json) * 32));
                    $matches = [];
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
            /**
             * @psalm-suppress UnusedFunctionCall Reset json_last_error before invoking user constructors.
             * @mago-expect analysis:unused-statement Reset json_last_error before invoking user constructors.
             */
            json_validate('null');
            $factory = $projection ? $compiled->factory : $compiled->orderedFactory;
            return $factory($matches, false);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @param class-string $class
     * @return Plan|DecodeError
     * @throws \ReflectionException */
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
                'property' => $reflection->getProperty($meta->name),
                'validator' => ConstructorValueValidator::forParameter($meta, [$meta->name => true]),
                'converter' => new FieldValueConverter($parameter, $collection),
                'type' => self::directType($parameter, $collection),
                'kind' => $meta->type instanceof ReflectionNamedType && $meta->builtin
                    ? $meta->type->getName()
                    : 'convert',
                'nullable' => $parameter->allowsNull(),
                'enumCases' => self::enumCases($parameter),
                'scalarNames' => $parameter->getType() instanceof ReflectionUnionType
                    ? array_map(
                        static fn(\ReflectionType $type): string => (string) $type,
                        $parameter->getType()->getTypes(),
                    )
                    : null,
            ];
        }
        $properties = PublicProperties::resolve($reflection);
        if ($properties instanceof DecodeError) {
            return $properties;
        }
        $directProperties = [];
        foreach ($properties as $name => $property) {
            $collection = FieldTypeResolver::resolve($class, $property['property']);
            if ($collection instanceof DecodeError) {
                return $collection;
            }
            $property['validator'] = null;
            $property['type'] = self::directType($property['property'], $collection);
            $property['kind'] = $property['builtinType']?->getName() ?? 'convert';
            $property['nullable'] = $property['property']->getType()?->allowsNull() ?? false;
            $property['enumCases'] = self::enumCases($property['property']);
            $property['scalarNames'] = $property['property']->getType() instanceof ReflectionUnionType
                ? array_map(
                    static fn(\ReflectionType $type): string => (string) $type,
                    $property['property']->getType()->getTypes(),
                )
                : null;
            unset($property['builtinType']);
            $directProperties[$name] = $property;
        }
        unset($property);
        return self::$plans[$class] = [
            $arguments,
            $directProperties,
            $arguments + $directProperties,
            $reflection->getName(),
        ];
    }

    /**
     * @return string|ListType|MapType|TupleType|CollectionUnionType|null
     */
    private static function directType(
        \ReflectionParameter|\ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection,
    ): string|ListType|MapType|TupleType|CollectionUnionType|null {
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

    /**
     * @return array<string, array<int|string, \BackedEnum>>|null
     * @throws \ReflectionException
     */
    private static function enumCases(\ReflectionParameter|\ReflectionProperty $field): array|null
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
        foreach (new \ReflectionEnum($name)->getCases() as $reflectionCase) {
            $case = $reflectionCase->getValue();
            if (!$case instanceof \BackedEnum) {
                return null;
            }
            $cases[get_debug_type($case->value)][$case->value] = $case;
        }
        return $cases;
    }

    /**
     * @param class-string $class
     */
    private function object(string $class, string $path, int|null $objectEnd = null): object
    {
        $start = $this->position;
        try {
            $plan = self::plan($class);
            if ($plan instanceof DecodeError) {
                // No constructor has run for this object: reuse declaration/error contracts.
                $this->skip();
                /** @var stdClass $raw The source interval begins with a validated object token. */
                $raw = json_decode(substr($this->json, $start, $this->position - $start));
                return ObjectHydrator::hydrate($class, $raw, $path);
            }
            [$arguments, $properties, $known, $displayClass] = $plan;
            if ((($objectEnd ?? strlen($this->json)) - $start) <= 32_768) {
                $compiled = self::$compiled[$class] ??= self::compile($class, $arguments, $properties);
                if ($compiled !== false) {
                    $source = (strlen($this->json) - $start) > 32_768
                        ? substr($this->json, $start, 32_768)
                        : $this->json;
                    $offset = $source === $this->json ? $start : 0;
                    $matches = [];
                    $numbered =
                        preg_match($compiled->orderedPattern, $source, $matches, PREG_UNMATCHED_AS_NULL, $offset) === 1;
                    if (
                        $numbered
                        || preg_match($compiled->unorderedPattern, $source, $matches, PREG_UNMATCHED_AS_NULL, $offset)
                            === 1
                    ) {
                        $factory = $numbered ? $compiled->orderedFactory : $compiled->factory;
                        $object = $factory($matches, false);
                        if ($object !== false) {
                            $this->position += strlen($matches[0] ?? '');
                            return $object;
                        }
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
                            $values[$name] = new DirectJsonSpan($begin, $this->position, $char);
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
                $raw = $value instanceof DirectJsonSpan ? ($value->kind === '{' ? new stdClass() : []) : $value;
                $error = $field['validator']->validate($displayClass, $name, $raw, '');
                if ($error !== null) {
                    return $field['validator']->validate($displayClass, $name, $raw, $path) ?? $error;
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
                $property = $properties[$name] ?? null;
                if ($property === null) {
                    continue;
                }
                $value = $this->field($displayClass, $this->fieldPath($path, $name), $property, $value);
                if ($value instanceof DecodeError) {
                    return $value;
                }
                $assignments[] = [$property['property'], $value];
            }
            /**
             * @psalm-suppress MixedMethodCall The validated declaration supplies the constructor and named argument types.
             * @mago-expect analysis:unknown-class-instantiation The target constructor is intentionally dynamic.
             */
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

    /**
     * @param class-string $class
     * @param Field $field
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws \UnhandledMatchError|\LogicException|\ReflectionException|\JsonException */
    private function field(
        string $class,
        string $path,
        array $field,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        if ($value instanceof DirectJsonSpan) {
            $type = $field['type'];
            if (is_string($type) && class_exists($type) && $value->kind === '{') {
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
            /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
            $value = json_decode(substr($this->json, $value->start, $value->end - $value->start));
        }

        return $field['converter']->convert($class, $value, $path);
    }

    /**
     * @param class-string $class
     * @return array<array-key, mixed>|object
     * @throws \UnhandledMatchError|\LogicException|\ReflectionException|\JsonException */
    private function collection(
        string $class,
        string $path,
        ListType|MapType|TupleType $type,
        int|null $end = null,
    ): array|object {
        $start = $this->position;
        $map = $type instanceof MapType;
        if ($this->json[$start] !== ($map ? '{' : '[')) {
            return CollectionValueConverter::convert($class, $path, $type, $this->native());
        }
        $item = $type instanceof ListType ? $type->itemType : ($type instanceof MapType ? $type->valueType : 'null');
        // Native bulk scalar conversion is much cheaper than a PHP loop.
        if (is_string($item) && in_array($item, ['string', 'int', 'float', 'bool'], true)) {
            if ($end !== null) {
                $this->position = $end;
                return CollectionValueConverter::convert(
                    $class,
                    $path,
                    $type,
                    json_decode(substr($this->json, $start, $end - $start)),
                );
            }
            return CollectionValueConverter::convert($class, $path, $type, $this->native());
        }
        // Map key and tuple length checks must precede child constructors.
        if ($type instanceof MapType || $type instanceof TupleType) {
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
            $bad = $type instanceof MapType
                ? !$type->arrayObject && $spans === [] || count(array_filter(array_keys($spans), 'is_int')) > 0
                : count($spans) < $type->required || count($spans) > count($type->types);
            if ($bad) {
                return CollectionValueConverter::convert(
                    $class,
                    $path,
                    $type,
                    json_decode(substr($this->json, $start, $end - $start)),
                );
            }
            $out = [];
            foreach ($spans as $key => $begin) {
                $this->position = $begin;
                $value = $this->item(
                    $class,
                    $this->indexPath($path, $key, $map),
                    $type instanceof MapType ? $item : $type->types[$key] ?? 'null',
                );
                if ($value instanceof DecodeError) {
                    return $value;
                }
                $out[$key] = $value;
            }
            $this->position = $end;
            return $type instanceof MapType && $type->arrayObject ? new ArrayObject($out) : $out;
        }
        ++$this->position;
        $this->white();
        $out = [];
        if ($this->json[$this->position] === ']') {
            ++$this->position;
            return $type->nonEmpty ? CollectionValueConverter::convert($class, $path, $type, []) : [];
        }
        $index = 0;
        while (true) {
            if (is_string($item) && !enum_exists($item) && class_exists($item)) {
                $plan = self::plan($item);
                $compiled = $plan instanceof DecodeError
                    ? false
                    : (self::$compiled[$item] ??= self::compile($item, $plan[0], $plan[1]));
                if ($compiled !== false) {
                    $source = substr($this->json, $this->position, 8192);
                    $rows = [];
                    $matched = preg_match_all(
                        $compiled->windowPattern,
                        $source,
                        $rows,
                        PREG_PATTERN_ORDER | PREG_UNMATCHED_AS_NULL,
                    );
                    if ($matched !== false && $matched > 0) {
                        try {
                            $complete = ($compiled->columns)($rows, $out, $this->position, $index);
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
                        continue;
                    }
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
            if ($separator !== ',') {
                return $out;
            }
        }
    }

    /**
     * @param class-string $class
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws \UnhandledMatchError|\LogicException|\ReflectionException|\JsonException */
    private function item(
        string $class,
        string $path,
        string|CollectionUnionType|NestedCollectionType $type,
    ): array|bool|float|int|object|string|null {
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

        return CollectionItemValueConverter::convert($class, $path, $type, $value);
    }

    private function fieldPath(string $parent, string $name): string
    {
        return FieldPath::field($parent, $name);
    }

    /** @throws \JsonException */
    private function indexPath(string $parent, int|string $key, bool $map = false): string
    {
        return $parent . ($map ? FieldPath::key('', (string) $key) : '[' . $key . ']');
    }

    /** @return array<array-key, mixed>|bool|float|int|object|string|null */
    private function native(): array|bool|float|int|object|string|null
    {
        $start = $this->position;
        $this->skip();
        /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
        $value = json_decode(substr($this->json, $start, $this->position - $start));
        return $value;
    }

    private function white(): void
    {
        $this->position += strspn($this->json, " \t\r\n", $this->position);
    }

    private function scalar(): bool|float|int|string|null
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
        /** @var numeric-string $number The entire document has already passed JSON validation. */
        /** @psalm-suppress InvalidOperand Adding zero intentionally preserves PHP integer-overflow conversion. */
        return strpbrk($number, '.eE') === false ? $number + 0 : (float) $number;
    }

    private function string(): string
    {
        $start = $this->position++;
        $this->position += strcspn($this->json, "\"\\", $this->position);
        if ($this->json[$this->position] === '"') {
            $out = substr($this->json, $start + 1, $this->position - $start - 1);
            ++$this->position;
            return $out;
        }
        while ($this->json[$this->position] !== '"') {
            $this->position += 2;
            $this->position += strcspn($this->json, "\"\\", $this->position);
        }
        ++$this->position;
        $token = substr($this->json, $start, $this->position - $start);
        /** @var string $value The token is a validated JSON string. */
        $value = json_decode($token);
        return $value;
    }

    private function skip(): void
    {
        $char = $this->json[$this->position];
        if ($char === '[' || $char === '{') {
            // \K leaves an empty match: report its end offset without copying the subtree.
            $pattern = '~(?(DEFINE)(?<s>"(?:[^"\\\\]++|\\\\.)*+")(?<o>\{(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\})(?<a>\[(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\]))\G(?:(?&o)|(?&a))\K~s';
            $match = [];
            $matched = preg_match($pattern, $this->json, $match, PREG_OFFSET_CAPTURE, $this->position);
            if ($matched === false && preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
                // This possessive scanner has linear work. Raise only its work budget,
                // then restore the setting before any target constructor can observe it.
                $limit = ini_get('pcre.backtrack_limit');
                try {
                    ini_set('pcre.backtrack_limit', (string) max((int) $limit, strlen($this->json) * 16));
                    $match = [];
                    $matched = preg_match($pattern, $this->json, $match, PREG_OFFSET_CAPTURE, $this->position);
                } finally {
                    ini_set('pcre.backtrack_limit', $limit);
                }
            }
            if ($matched === 1) {
                /**
                 * @var array{array{string, int<-1, max>}} $match
                 */
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

    /**
     * @param class-string $class
     * @param array<string, Field> $arguments
     * @param array<string, Field> $properties
     * @return DirectScalarPlan|false
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError */
    private static function compile(string $class, array $arguments, array $properties): DirectScalarPlan|false
    {
        // Resolve aliases to the declared PHP identifier before generating code.
        $class = new ReflectionClass($class)->getName();
        if (str_contains($class, '@')) {
            return false;
        }
        $alternatives = [];
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
            $alternatives[] =
                preg_quote(json_encode($name, JSON_THROW_ON_ERROR), '~')
                . '\s*+:\s*+(?<'
                . $capture
                . '>'
                . $token
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
                default => $native,
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
        unset($name, $field);
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
        unset($name, $field);
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
        /** @var \Closure(array<array-key, string|null>, bool): (object|false) $factory */
        $factory = eval($body);
        $numberedBody = preg_replace_callback(
            '/\$m\[\'v([0-9]+)\'\]/',
            /**
             * @param array<array-key, string> $match
             * @mago-expect analysis:possibly-undefined-int-array-index The capture always contains the numbered group.
             */
            static fn(array $match): string => '$m[' . ((int) $match[1] + 1) . ']',
            $body,
        );
        if ($numberedBody === null) {
            return false;
        }
        /** @var \Closure(array<array-key, string|null>, bool): (object|false) $numberedFactory */
        $numberedFactory = eval($numberedBody);
        $opening = strpos($numberedBody, '{');
        if ($opening === false) {
            return false;
        }
        $columnBody = substr($numberedBody, $opening + 1, -3);
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
        /** @var \Closure(array<array-key, array<array-key, string|null>>, array<array-key, mixed>, int, int): bool $columnFactory */
        $columnFactory =
            eval('return static function(array $columns, array &$out, int &$position, int &$index) use ($class, $fields, $properties): bool { $count = count($columns[0]); for ($i = 0; $i < $count; ++$i) {'
                . $columnBody
                . '} return true; };');
        /** @var non-falsy-string|null $numbered Named captures are the only replaced text; delimiters remain. */
        $numbered = preg_replace('/\(\?<v[0-9]+>/', '(', $ordered);
        if ($numbered === null) {
            return false;
        }
        $window = substr($numbered, 0, -1) . '\s*+(?:,\s*+|(?=\]))~';
        $fused =
            DirectGraphCompiler::strictTokens(str_replace(
                '\\G',
                '\\A[\\x20\\x09\\x0a\\x0d]*+',
                substr($numbered, 0, -1),
            )) . '[\\x20\\x09\\x0a\\x0d]*+\\z~u';
        $projection = $properties === [] ? DirectGraphCompiler::projection($alternatives, array_keys($fields)) : false;
        return new DirectScalarPlan(
            $pattern,
            $numbered,
            $window,
            $fused,
            $projection,
            $projection === false ? false : DirectGraphCompiler::asciiPattern($projection),
            $factory,
            $numberedFactory,
            $columnFactory,
        );
    }

    /**
     * @param class-string|JsonType<mixed> $type
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     */
    private static function fallback(string $json, string|JsonType $type): mixed
    {
        /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
        $value = NativeJsonDecoder::decode($json, $type);
        return $value;
    }
}
