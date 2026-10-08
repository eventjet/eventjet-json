<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use ReflectionClass;
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
 * @mago-expect lint:no-else-clause Mutually exclusive grammar and cursor transitions stay together.
 * @mago-expect lint:literal-named-argument Scanner and code-emitter calls use positional arguments consistently.
 * @mago-expect lint:no-eval Only reflected declarations and fixed emitter strings become PHP; JSON never becomes code.
 * @mago-expect lint:no-error-control-operator Unsupported generated PCRE patterns take the compatibility fallback.
 * @mago-expect lint:inline-variable-return Local annotations document the validated-token and emitted-closure boundaries.
 * @mago-expect analysis:side-effects-in-condition Short-circuit validation preserves autoload and failure ordering.
 * @phpstan-type Field array{validator: ConstructorValueValidator|null, converter: FieldValueConverter, property: \ReflectionProperty, type: string|ListType|MapType|TupleType|CollectionUnionType|null, kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames: array<string>|null}
 * @phpstan-type Plan array{array<string, Field>, array<string, Field>, array<string, Field>, class-string}
 * @phpstan-type ScalarField array{kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames?: array<string>|null, ...}
 * @psalm-type Field array{validator: ConstructorValueValidator|null, converter: FieldValueConverter, property: \ReflectionProperty, type: string|ListType|MapType|TupleType|CollectionUnionType|null, kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames: array<string>|null}
 * @psalm-type Plan array{array<string, Field>, array<string, Field>, array<string, Field>, class-string}
 * @psalm-type ScalarField array{kind: string, nullable: bool, enumCases: array<string, array<int|string, \BackedEnum>>|null, scalarNames?: array<string>|null, ...}
 */
final class DirectGraphCompiler
{
    private const string WS = '[\x20\x09\x0a\x0d]*+';
    private const string STRING = '"(?:[^"\\\\\x00-\x1f]++|\\\\(?:["\\\\/bfnrt]|u(?:[0-9a-cA-Ce-fE-F][0-9a-fA-F]{3}|[dD][0-7][0-9a-fA-F]{2}|[dD][89aAbB][0-9a-fA-F]{2}\\\\u[dD][c-fC-F][0-9a-fA-F]{2})))*+"';
    private const string NUMBER = '-?(?:0|[1-9][0-9]*+)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?';

    /** @var array<string, array{non-empty-string, Closure(string): (array<array-key, mixed>|bool|float|int|object|string|null), bool}|false> */
    private static array $cache = [];
    /** @var array<class-string, int> */
    private array $nodes = [];
    /** @var array<int|string, string> */
    private array $definitions = [];
    /** @var array<int, string> */
    private array $bodies = [];
    /** @var list<\ReflectionProperty> */
    private array $properties = [];
    /** @var list<array<int|string, \BackedEnum>> */
    private array $enumValues = [];
    private int $serial = 0;
    private bool $recursive = false;

    /**
     * @param Closure(class-string): (Plan|DecodeError) $plans
     * */
    private function __construct(
        private Closure $plans,
        private bool $compact,
    ) {}

    public static function strictTokens(string $pattern): string
    {
        return str_replace(['"(?:[^"\\\\]++|\\\\.)*+"', '\\s*+'], [self::STRING, self::WS], $pattern);
    }

    /** @return non-empty-string */
    public static function asciiPattern(string $pattern): string
    {
        $ascii = str_replace('\\x00-\\x1f]', '\\x00-\\x1f\\x80-\\xff]', substr($pattern, 0, -1));
        return $ascii === '' ? '~(?!)~' : $ascii;
    }

    /**
     * @param list<string> $alternatives
     * @param list<string> $names
     * @throws \JsonException
     * @return non-empty-string
     */
    public static function projection(array $alternatives, array $names): string
    {
        $definitions = '(?<j0>' . self::STRING . '|' . self::NUMBER . '|true|false|null)';
        for ($depth = 1; $depth <= 3; ++$depth) {
            $value = '(?&j' . ($depth - 1) . ')';
            $array = '\\[' . self::WS . '(?:' . $value . self::WS . '(?:,' . self::WS . $value . self::WS . ')*+)?\\]';
            $member = self::STRING . self::WS . ':' . self::WS . $value . self::WS;
            $object = '\\{' . self::WS . '(?:' . $member . '(?:,' . self::WS . $member . ')*+)?\\}';
            $definitions .= '(?<j' . $depth . '>(?&j0)|' . $array . '|' . $object . ')';
        }
        $keys = implode('|', array_map(static fn(string $name): string => preg_quote(
            json_encode($name, JSON_THROW_ON_ERROR),
            '~',
        ), $names));
        $unknown =
            '(?!(?:' . $keys . ')' . self::WS . ':)"[^"\\\\\\x00-\\x1f]*+"' . self::WS . ':' . self::WS . '(?&j3)';
        // Bound retained known-string captures; unusually long/escaped known
        // strings fall back. Ignored strings keep the allocation-free grammar.
        $known = str_replace(
            self::STRING,
            '"[^"\\\\\\x00-\\x1f]{0,4096}"',
            self::strictTokens(implode('|', $alternatives)),
        );
        $member = '(?:' . $known . '|' . $unknown . ')';
        return (
            '~(?(DEFINE)'
            . $definitions
            . ')\\A'
            . self::WS
            . '\\{'
            . self::WS
            . '(?:'
            . $member
            . '(?:'
            . self::WS
            . ','
            . self::WS
            . $member
            . ')*+)?'
            . self::WS
            . '\\}'
            . self::WS
            . '\\z\\K~Ju'
        );
    }

    /**
     * @param class-string|JsonType<mixed> $type
     * @param Closure(class-string): (Plan|DecodeError) $plans
     * @return array<array-key, mixed>|bool|float|int|object|string|null */
    public static function decode(
        string $json,
        string|JsonType $type,
        Closure $plans,
        bool $compact,
    ): array|bool|float|int|object|string|null {
        $key = ($compact ? 'compact:' : 'flex:') . (is_string($type) ? $type : serialize($type));
        if (!array_key_exists($key, self::$cache)) {
            // Do not resolve declarations/autoload classes on malformed cold input.
            if (!json_validate($json)) {
                return false;
            }
            try {
                $compiler = new self($plans, $compact);
                $root = is_string($type) ? $compiler->object($type) : $compiler->value($type->collectionItem());
                if ($root === false) {
                    self::$cache[$key] = false;
                } else {
                    [$pattern, $expression] = $root;
                    $pattern =
                        '~(?(DEFINE)(?<S>'
                        . self::STRING
                        . ')(?<I>'
                        . self::integerPattern()
                        . ')(?<N>'
                        . self::NUMBER
                        . ')'
                        . implode('', $compiler->definitions)
                        . ')\A'
                        . $compiler->whitePattern()
                        . $pattern
                        . $compiler->whitePattern()
                        . '\z\K~u';
                    if (@preg_match($pattern, '') === false) {
                        self::$cache[$key] = false;
                        return false;
                    }
                    $functions = [];
                    $properties = $compiler->properties;
                    $enumValues = $compiler->enumValues;
                    foreach ($compiler->bodies as $id => $body) {
                        $body = str_replace(
                            '$key = self::string($s, $p);',
                            $compiler->assignment(
                                '$key',
                                'self::string($s, $p)',
                                ['type' => null, 'kind' => 'string', 'nullable' => false],
                                null,
                            ),
                            $body,
                        );
                        $functions[$id] = self::generatedNode($body, $functions, $properties, $enumValues);
                    }
                    $rootFunction = self::generatedRoot(
                        $expression,
                        $compiler->whiteCode(),
                        $functions,
                        $properties,
                        $enumValues,
                    );
                    self::$cache[$key] = [
                        $pattern,
                        $rootFunction,
                        !$compiler->recursive && count($compiler->definitions) < 512,
                    ];
                }
            } catch (Throwable) {
                self::$cache[$key] = false;
            }
        }
        /**
         * @psalm-suppress UnnecessaryVarAnnotation Mago does not preserve cache-key existence across compilation.
         * @var array{non-empty-string, Closure(string): (array<array-key, mixed>|bool|float|int|object|string|null), bool}|false $compiled
         * @mago-expect analysis:possibly-undefined-string-array-index Both compilation outcomes populate this key.
         */
        $compiled = self::$cache[$key];
        if ($compiled === false) {
            return false;
        }
        // Static schema recursion can exceed JSON's depth limit. Use the native
        // depth check conservatively when total opening delimiters reaches 512.
        if (!$compiled[2] && (substr_count($json, '{') + substr_count($json, '[')) >= 512 && !json_validate($json)) {
            return false;
        }
        if (preg_match($compiled[0], $json) !== 1) {
            return false;
        }
        /**
         * @psalm-suppress UnusedFunctionCall Reset json_last_error before invoking user constructors.
         * @mago-expect analysis:unused-statement Reset json_last_error before invoking user constructors.
         */
        json_validate('null');
        return $compiled[1]($json);
    }

    /**
     * @return array{string, string}|false
     * @param class-string $class
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function object(string $class): array|false
    {
        if (isset($this->nodes[$class])) {
            $id = $this->nodes[$class];
            $this->recursive = $this->recursive || !isset($this->bodies[$id]);
            return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
        }
        $plan = ($this->plans)($class);
        if ($plan instanceof DecodeError || str_contains($class, '@')) {
            return false;
        }
        [$arguments, $properties, $fields, $display] = $plan;
        $class = new ReflectionClass($class)->getName();
        $id = $this->serial++;
        $this->nodes[$class] = $id;
        $optional = [];
        foreach (new ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
            /** @mago-expect analysis:impossible-condition Reflection parameters can be optional in supported schemas. */
            if (!$parameter->isOptional()) {
                continue;
            }

            $optional[$parameter->getName()] = true;
        }
        if (!$this->compact && $optional !== []) {
            return $this->optionalObject($class, $id, $arguments, $properties, $optional);
        }
        if (!$this->compact && $properties !== []) {
            return $arguments === []
                ? $this->registeredObject($class, $id, $properties, $display)
                : $this->interleavedObject($class, $id, $arguments, $properties, $display);
        }
        $parts = [];
        $body = '++$p;' . $this->whiteCode();
        $values = [];
        foreach ($fields as $name => $field) {
            $value = $this->fieldValue($field);
            if ($value === false) {
                return false;
            }
            [$pattern, $expression] = $value;
            if ($field['nullable'] && $field['type'] !== null) {
                $pattern = '(?:null|' . $pattern . ')';
                $expression = '($s[$p] === "n" ? self::null($p) : ' . $expression . ')';
            }
            $key = json_encode($name, JSON_THROW_ON_ERROR);
            $parts[] = preg_quote($key, '~') . $this->whitePattern() . ':' . $this->whitePattern() . $pattern;
            $local = '$a' . count($values);
            $values[] = $local;
            $body .=
                (
                    $this->compact
                        ? '$p += ' . (strlen($key) + 1) . ';'
                        : '$p += ' . strlen($key) . ';' . $this->whiteCode() . '++$p;' . $this->whiteCode()
                )
                . $this->assignment($local, $expression, $field, count($values) === count($fields) ? '}' : ',')
                . $this->whiteCode()
                . '++$p;'
                . (count($values) === count($fields) ? '' : $this->whiteCode());
        }
        if ($fields === []) {
            $body .= '++$p;';
        }
        $this->definitions[$id] =
            '(?<n'
            . $id
            . '>\{'
            . $this->whitePattern()
            . implode($this->whitePattern() . ',' . $this->whitePattern(), $parts)
            . $this->whitePattern()
            . '\})';
        $body .=
            'try { $object = new \\' . $class . '(' . implode(',', array_slice($values, 0, count($arguments))) . ');';
        $index = count($arguments);
        foreach ($properties as $property) {
            $slot = count($this->properties);
            $this->properties[] = $property['property'];
            $body .= '$properties[' . $slot . ']->setValue($object, $a' . $index++ . ');';
        }
        $body .=
            'return $object; } catch (\\Throwable $error) { return \\Eventjet\\Json\\DecodeError::cannotInstantiate('
            . var_export($display, true)
            . ', $error); }';
        $this->bodies[$id] = $body;
        return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
    }

    /**
     * @return array{string, string}|false
     * @param class-string $class
     * @param array<string, Field> $properties
     * @param array<string, Field> $arguments
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function interleavedObject(
        string $class,
        int $id,
        array $arguments,
        array $properties,
        string $display,
    ): array|false {
        $argumentPatterns = [];
        $propertyPatterns = [];
        $expressions = [];
        $switch = '';
        $convert = '';
        $assign = '';
        foreach ($arguments + $properties as $name => $field) {
            $value = $this->fieldValue($field);
            if ($value === false) {
                return false;
            }
            [$pattern, $expression] = $value;
            if ($field['nullable'] && $field['type'] !== null) {
                $pattern = '(?:null|' . $pattern . ')';
                $expression = '($s[$p] === "n" ? self::null($p) : ' . $expression . ')';
            }
            $member =
                preg_quote(json_encode($name, JSON_THROW_ON_ERROR), '~')
                . $this->whitePattern()
                . ':'
                . $this->whitePattern()
                . $pattern;
            $switch .= 'case ' . var_export($name, true) . ':';
            if (isset($arguments[$name])) {
                $argumentPatterns[] = $member;
                $local = '$a' . count($expressions);
                $expressions[] = $local;
                $switch .= $this->assignment($local, $expression, $field, null) . ' break;';
            } else {
                $propertyPatterns[$name] = $pattern;
                $slot = count($this->properties);
                $this->properties[] = $field['property'];
                if ($field['type'] === null) {
                    $switch .=
                        $this->assignment('$value', $expression, $field, null)
                        . '$assignments[] = ['
                        . $slot
                        . ', $value]; break;';
                } else {
                    $switch .= '$assignments[] = [' . $slot . ', $p]; self::skip($s, $p); break;';
                    $convert .=
                        'case '
                        . $slot
                        . ': $p = $value; $value = '
                        . $expression
                        . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } break;';
                }
                $write = self::needsReflection($field['property'])
                    ? '$properties[' . $slot . ']->setValue($object, $value);'
                    : '$object->{' . var_export($name, true) . '} = $value;';
                $assign .= 'case ' . $slot . ':' . $write . 'break;';
            }
        }
        $this->definitions['p' . $id] = '(?<p' . $id . '>' . $this->memberChoices($propertyPatterns) . ')';
        $member = '(?&p' . $id . ')';
        $separator = $this->whitePattern() . ',' . $this->whitePattern();
        $gap = '(?:' . $member . $separator . ')*+';
        $content = $gap . implode($separator . $gap, $argumentPatterns) . '(?:' . $separator . $member . ')*+';
        $this->definitions[$id] =
            '(?<n' . $id . '>\{' . $this->whitePattern() . $content . $this->whitePattern() . '\})';
        $body =
            '++$p;'
            . $this->whiteCode()
            . '$assignments = []; do { $key = self::string($s, $p);'
            . $this->whiteCode()
            . '++$p;'
            . $this->whiteCode()
            . 'switch ($key) {'
            . $switch
            . '}'
            . $this->whiteCode()
            . '$separator = $s[$p++];'
            . $this->whiteCode()
            . '} while ($separator === ","); $end = $p;'
            . 'foreach ($assignments as &$assignment) { [$slot, $value] = $assignment; switch ($slot) {'
            . $convert
            . '} $assignment[1] = $value; } unset($assignment); $p = $end;'
            . 'try { $object = new \\'
            . $class
            . '('
            . implode(',', $expressions)
            . ');'
            . 'foreach ($assignments as [$slot, $value]) { switch ($slot) {'
            . $assign
            . '} } return $object;'
            . '} catch (\\Throwable $error) { return \\Eventjet\\Json\\DecodeError::cannotInstantiate('
            . var_export($display, true)
            . ', $error); }';
        $this->bodies[$id] = $body;
        return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
    }

    /**
     * @return array{string, string}|false
     * @param class-string $class
     * @param array<string, Field> $properties
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function registeredObject(string $class, int $id, array $properties, string $display): array|false
    {
        $reflection = new ReflectionClass($class);
        $direct = $reflection->getConstructor() === null && !$reflection->hasMethod('__set');
        foreach ($properties as $field) {
            $direct = $direct && !self::needsReflection($field['property']) && $field['property']->getHooks() === [];
        }
        $members = [];
        $switch = '';
        $assign = '';
        $index = 0;
        foreach ($properties as $name => $field) {
            $value = $this->fieldValue($field);
            if ($value === false) {
                return false;
            }
            [$pattern, $expression] = $value;
            if ($field['nullable'] && $field['type'] !== null) {
                $pattern = '(?:null|' . $pattern . ')';
                $expression = '($s[$p] === "n" ? self::null($p) : ' . $expression . ')';
            }
            $members[$name] = $pattern;
            $local = '$v' . $index;
            $switch .= 'case ' . var_export($name, true) . ':' . $this->assignment($local, $expression, $field, null);
            $slot = count($this->properties);
            $this->properties[] = $field['property'];
            $write = self::needsReflection($field['property'])
                ? '$properties[' . $slot . ']->setValue($object, ' . $local . ');'
                : '$object->{' . var_export($name, true) . '} = ' . $local . ';';
            if ($direct) {
                $mask = '$seen' . intdiv($index, 63);
                $bit = (string) (1 << ($index % 63));
                $switch .= $mask . '|=' . $bit . '; break;';
                $assign .= 'if (' . $mask . ' & ' . $bit . ') {' . $write . '}';
            } else {
                $switch .= '$slots[] = ' . $index . '; break;';
                $assign .= 'case ' . $index . ':' . $write . 'break;';
            }
            ++$index;
        }
        $this->definitions['p' . $id] = '(?<p' . $id . '>' . $this->memberChoices($members) . ')';
        $member = '(?&p' . $id . ')';
        $this->definitions[$id] =
            '(?<n'
            . $id
            . '>\{'
            . $this->whitePattern()
            . '(?:'
            . $member
            . '(?:'
            . $this->whitePattern()
            . ','
            . $this->whitePattern()
            . $member
            . ')*+)?'
            . $this->whitePattern()
            . '\})';
        $initialize = '$slots = [];';
        if ($direct) {
            $initialize = '';
            for ($group = 0; $group < (int) ceil($index / 63); ++$group) {
                $initialize .= '$seen' . $group . '=0;';
            }
        }
        $this->bodies[$id] =
            '++$p;'
            . $this->whiteCode()
            . $initialize
            . 'if ($s[$p] !== "}") { do { $key = self::string($s, $p);'
            . $this->whiteCode()
            . '++$p;'
            . $this->whiteCode()
            . 'switch ($key) {'
            . $switch
            . '}'
            . $this->whiteCode()
            . '$separator = $s[$p++];'
            . $this->whiteCode()
            . '} while ($separator === ","); } else { ++$p; }'
            . 'try { $object = new \\'
            . $class
            . '();'
            . ($direct ? $assign : 'foreach ($slots as $slot) { switch ($slot) {' . $assign . '} }')
            . 'return $object;'
            . '} catch (\\Throwable $error) { return \\Eventjet\\Json\\DecodeError::cannotInstantiate('
            . var_export($display, true)
            . ', $error); }';
        return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
    }

    /**
     * @return array{string, string}|false
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function value(string|ListType|MapType|TupleType|CollectionUnionType|NestedCollectionType $type): array|false
    {
        if ($type instanceof CollectionUnionType) {
            $parts = [];
            $branches = [];
            foreach ($type->members as $member) {
                $value = $this->value($member);
                if ($value === false) {
                    return false;
                }
                $parts[] = $value[0];
                $char = $member instanceof NestedCollectionType
                    ? ($member->collection instanceof MapType ? '{' : '[')
                    : match ($member) {
                        'string' => '"',
                        'bool' => 't',
                        'null' => 'n',
                        'int', 'float' => '-',
                        default => '{',
                    };
                // Ambiguous scalar/collection unions retain the existing converter.
                if (isset($branches[$char]) || in_array($char, ['t', '-'], true)) {
                    return false;
                }
                $branches[$char] = $value[1];
            }
            $expression = 'match ($s[$p]) {';
            foreach ($branches as $char => $branch) {
                $expression .= var_export($char, true) . ' => ' . $branch . ',';
            }
            return ['(?:' . implode('|', $parts) . ')', $expression . '}'];
        }
        if ($type instanceof NestedCollectionType) {
            $value = $this->value($type->collection);
            return (
                $value === false || !$type->nullable
                    ? $value
                    : ['(?:null|' . $value[0] . ')', '($s[$p] === "n" ? self::null($p) : ' . $value[1] . ')']
            );
        }
        if (is_string($type)) {
            if (in_array($type, ['string', 'int', 'float', 'bool', 'null', 'true', 'false'], true)) {
                return $this->scalar(['kind' => $type, 'nullable' => false, 'enumCases' => null]);
            }
            if (is_subclass_of($type, \BackedEnum::class)) {
                $cases = [];
                foreach (new \ReflectionEnum($type)->getCases() as $reflectionCase) {
                    $case = $reflectionCase->getValue();
                    if (!$case instanceof \BackedEnum) {
                        return false;
                    }
                    $cases[get_debug_type($case->value)][$case->value] = $case;
                }
                return $this->scalar(['kind' => 'convert', 'nullable' => false, 'enumCases' => $cases]);
            }
            return enum_exists($type) || !class_exists($type) ? false : $this->object($type);
        }
        if ($type instanceof TupleType) {
            $parts = [];
            $body = '++$p;' . $this->whiteCode() . '$out = [];';
            foreach ($type->types as $index => $member) {
                $value = $this->value($member);
                if ($value === false) {
                    return false;
                }
                $parts[] = $value[0];
                if ($index >= $type->required) {
                    $body .= 'if ($s[$p] === "]") { ++$p; return $out; }';
                }
                $body .=
                    '$value = '
                    . $value[1]
                    . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } $out[] = $value;'
                    . $this->whiteCode()
                    . 'if ($s[$p] === ",") { ++$p;'
                    . $this->whiteCode()
                    . '}';
            }
            $body .= '++$p; return $out;';
            $content = '';
            for ($index = count($parts) - 1; $index >= 0; --$index) {
                $entry =
                    ($index > 0 ? ',' . $this->whitePattern() : '')
                    . ($parts[$index] ?? '')
                    . $this->whitePattern()
                    . $content;
                $content = $index >= $type->required ? '(?:' . $entry . ')?' : $entry;
            }
            $id = $this->serial++;
            $this->definitions[$id] = '(?<n' . $id . '>\[' . $this->whitePattern() . $content . '\])';
            $this->bodies[$id] = $body;
            return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
        }
        if ($type instanceof MapType) {
            $item = $this->value($type->valueType);
            if ($item === false) {
                return false;
            }
            [$pattern, $expression] = $item;
            $id = $this->serial++;
            // A conservative key subset proves keys remain strings in PHP arrays.
            // Escaped, numeric and unusual keys use the general decoder instead.
            $member =
                '"[A-Za-z_][^"\\\\\x00-\x1f]*+"'
                . $this->whitePattern()
                . ':'
                . $this->whitePattern()
                . $pattern
                . $this->whitePattern();
            $this->definitions[$id] =
                '(?<n'
                . $id
                . '>\{'
                . $this->whitePattern()
                . '(?:'
                . $member
                . '(?:,'
                . $this->whitePattern()
                . $member
                . ')*+)'
                . ($type->arrayObject ? '?' : '')
                . '\})';
            $result = $type->arrayObject ? 'new \\ArrayObject($out)' : '$out';
            if (is_string($type->valueType) && in_array($type->valueType, ['string', 'int', 'float', 'bool'], true)) {
                $this->bodies[$id] =
                    '$end = strpos($s, "}", $p) + 1; $out = json_decode(substr($s, $p, $end - $p), true);'
                    . 'if (!is_array($out)) { $start = $p; self::skip($s, $p); $out = json_decode(substr($s, $start, $p - $start), true); } else { $p = $end; }'
                    . ($type->valueType === 'float' ? '$out = array_map("floatval", $out);' : '')
                    . 'return '
                    . $result
                    . ';';
                return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
            }
            $this->bodies[$id] =
                '++$p;'
                . $this->whiteCode()
                . '$out = []; if ($s[$p] === "}") { ++$p; return '
                . $result
                . '; } do { $key = self::string($s, $p);'
                . $this->whiteCode()
                . '++$p;'
                . $this->whiteCode()
                . '$value = '
                . $expression
                . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } $out[$key] = $value;'
                . $this->whiteCode()
                . '$separator = $s[$p++];'
                . $this->whiteCode()
                . '} while ($separator === ","); return '
                . $result
                . ';';
            return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
        }
        $item = $this->value($type->itemType);
        if ($item === false) {
            return false;
        }
        [$pattern, $expression] = $item;
        $id = $this->serial++;
        $content =
            '(?:'
            . $pattern
            . $this->whitePattern()
            . '(?:,'
            . $this->whitePattern()
            . $pattern
            . $this->whitePattern()
            . ')*+)';
        $this->definitions[$id] =
            '(?<n' . $id . '>\[' . $this->whitePattern() . $content . ($type->nonEmpty ? '' : '?') . '\])';
        if (is_string($type->itemType) && in_array($type->itemType, ['string', 'int', 'float', 'bool'], true)) {
            $this->bodies[$id] =
                '$end = strpos($s, "]", $p) + 1; $out = json_decode(substr($s, $p, $end - $p));'
                . 'if (!is_array($out)) { $start = $p; self::skip($s, $p); $out = json_decode(substr($s, $start, $p - $start)); } else { $p = $end; }'
                . ($type->itemType === 'float' ? '$out = array_map("floatval", $out);' : '')
                . 'return $out;';
            return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
        }
        $this->bodies[$id] =
            '++$p;'
            . $this->whiteCode()
            . '$out = []; if ($s[$p] === "]") { ++$p; return $out; } do {'
            . '$value = '
            . $expression
            . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } $out[] = $value;'
            . $this->whiteCode()
            . '$separator = $s[$p++];'
            . $this->whiteCode()
            . '} while ($separator === ","); return $out;';
        if (
            $this->compact
            && is_string($type->itemType)
            && class_exists($type->itemType)
            && isset($this->nodes[$type->itemType])
        ) {
            $plan = ($this->plans)($type->itemType);
            $childId = $this->nodes[$type->itemType];
            if (
                !$plan instanceof DecodeError
                && isset($this->bodies[$childId])
                && count(array_filter(
                    $plan[2],
                    /** @param Field $field */ static fn(array $field): bool => $field['type'] !== null,
                )) === 0
            ) {
                // Scalar-only row bodies have no temporary child lifetimes to
                // change. Keep their exception returns local to the list decoder.
                $inline = str_replace('return $object;', '$value = $object;', $this->bodies[$childId]);
                $this->bodies[$id] = str_replace('$value = ' . $expression . ';', $inline, $this->bodies[$id]);
            }
        }
        return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
    }

    /**
     * @return array{string, string}|false
     * @param class-string $class
     * @param array<string, Field> $properties
     * @param array<string, Field> $arguments
     * @param array<string, true> $optional
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function optionalObject(
        string $class,
        int $id,
        array $arguments,
        array $properties,
        array $optional,
    ): array|false {
        $required = [];
        $extras = [];
        $switch = '';
        $deferredArguments = '';
        $convertProperties = '';
        $assign = '';
        foreach ($arguments + $properties as $name => $field) {
            $value = $this->fieldValue($field);
            if ($value === false) {
                return false;
            }
            [$pattern, $expression] = $value;
            if ($field['nullable'] && $field['type'] !== null) {
                $pattern = '(?:null|' . $pattern . ')';
                $expression = '($s[$p] === "n" ? self::null($p) : ' . $expression . ')';
            }
            $member =
                preg_quote(json_encode($name, JSON_THROW_ON_ERROR), '~')
                . $this->whitePattern()
                . ':'
                . $this->whitePattern()
                . $pattern;
            $key = var_export($name, true);
            $switch .= 'case ' . $key . ':';
            if (isset($arguments[$name])) {
                if (!isset($optional[$name])) {
                    $required[] = $member;
                    $switch .=
                        '$value = '
                        . $expression
                        . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } $ctor['
                        . $key
                        . '] = $value; break;';
                } else {
                    $extras[$name] = $pattern;
                    if ($field['type'] === null) {
                        $switch .= '$ctor[' . $key . '] = ' . $expression . '; break;';
                    } else {
                        $switch .= '$deferred[' . $key . '] = $p; self::skip($s, $p); break;';
                        $deferredArguments .=
                            'if (isset($deferred['
                            . $key
                            . '])) { $p = $deferred['
                            . $key
                            . ']; $value = '
                            . $expression
                            . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } $ctor['
                            . $key
                            . '] = $value; }';
                    }
                }
            } else {
                $extras[$name] = $pattern;
                $slot = count($this->properties);
                $this->properties[] = $field['property'];
                if ($field['type'] === null) {
                    $switch .= '$assignments[] = [' . $slot . ',' . $expression . ']; break;';
                } else {
                    $switch .= '$assignments[] = [' . $slot . ', $p]; self::skip($s, $p); break;';
                    $convertProperties .=
                        'case '
                        . $slot
                        . ': $p = $value; $value = '
                        . $expression
                        . '; if ($value instanceof \\Eventjet\\Json\\DecodeError) { return $value; } break;';
                }
                $write = self::needsReflection($field['property'])
                    ? '$properties[' . $slot . ']->setValue($object, $value);'
                    : '$object->{' . $key . '} = $value;';
                $assign .= 'case ' . $slot . ':' . $write . 'break;';
            }
        }
        $this->definitions['e' . $id] = '(?<e' . $id . '>' . $this->memberChoices($extras) . ')';
        $extra = '(?&e' . $id . ')';
        $separator = $this->whitePattern() . ',' . $this->whitePattern();
        $gap = '(?:' . $extra . $separator . ')*+';
        $content = $required === []
            ? '(?:' . $extra . '(?:' . $separator . $extra . ')*+)?'
            : $gap . implode($separator . $gap, $required) . '(?:' . $separator . $extra . ')*+';
        $this->definitions[$id] =
            '(?<n' . $id . '>\{' . $this->whitePattern() . $content . $this->whitePattern() . '\})';
        $this->bodies[$id] =
            '++$p;'
            . $this->whiteCode()
            . '$ctor = []; $deferred = []; $assignments = []; if ($s[$p] !== "}") { do { $key = self::string($s, $p);'
            . $this->whiteCode()
            . '++$p;'
            . $this->whiteCode()
            . 'switch ($key) {'
            . $switch
            . '}'
            . $this->whiteCode()
            . '$separator = $s[$p++];'
            . $this->whiteCode()
            . '} while ($separator === ","); } else { ++$p; } $end = $p;'
            . $deferredArguments
            . 'foreach ($assignments as &$assignment) { [$slot, $value] = $assignment; switch ($slot) {'
            . $convertProperties
            . '} $assignment[1] = $value; } unset($assignment); $p = $end;'
            . 'try { $object = new \\'
            . $class
            . '(...$ctor); foreach ($assignments as [$slot, $value]) { switch ($slot) {'
            . $assign
            . '} } return $object;'
            . '} catch (\\Throwable $error) { return \\Eventjet\\Json\\DecodeError::cannotInstantiate('
            . var_export($class, true)
            . ', $error); }';
        return ['(?&n' . $id . ')', '$functions[' . $id . ']($s, $p)'];
    }

    /**
     * @return array{string, string}|false
     * @param Field $field
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function fieldValue(array $field): array|false
    {
        $value = $field['type'] === null ? $this->scalar($field) : $this->value($field['type']);
        $scalarNames = $field['scalarNames'];
        if ($value === false || !is_string($field['type']) || $scalarNames === null) {
            return $value;
        }
        $names = array_values(array_intersect($scalarNames, [
            'string',
            'int',
            'float',
            'bool',
            'true',
            'false',
            'null',
        ]));
        if ($names === []) {
            return $value;
        }
        $scalar = $this->scalar([
            'kind' => 'convert',
            'enumCases' => null,
            'nullable' => false,
            'scalarNames' => $names,
        ]);
        if ($scalar === false) {
            return false;
        }
        return [
            '(?:' . $value[0] . '|' . $scalar[0] . ')',
            '($s[$p] === "{" ? ' . $value[1] . ' : ' . $scalar[1] . ')',
        ];
    }

    /**
     * @param array<string, string> $members
     */
    private function memberChoices(array $members): string
    {
        $suffix = $this->whitePattern() . ':' . $this->whitePattern();
        $parts = [];
        foreach ($members as $name => $pattern) {
            $parts[] = preg_quote(json_encode($name, JSON_THROW_ON_ERROR), '~') . $suffix . $pattern;
        }
        return implode('|', $parts);
    }

    /**
     * @return array{string, string}|false
     * @param ScalarField $field
     * @throws \LogicException|\ReflectionException|\JsonException|\UnhandledMatchError|\ArithmeticError */
    private function scalar(array $field): array|false
    {
        $kind = $field['kind'];
        $pattern = match ($kind) {
            'string' => '(?&S)',
            'int' => '(?&I)',
            'float' => '(?&N)',
            'bool' => '(?:true|false)',
            'true' => 'true',
            'false' => 'false',
            'null' => 'null',
            default => null,
        };
        if ($field['enumCases'] !== null) {
            $tokens = [];
            $lookup = [];
            foreach ($field['enumCases'] as $cases) {
                foreach ($cases as $case) {
                    $token = json_encode($case->value, JSON_THROW_ON_ERROR);
                    $tokens[] = preg_quote($token, '~');
                    $lookup[$token] = $case;
                }
            }
            if ($tokens === []) {
                return false;
            }
            $slot = count($this->enumValues);
            $this->enumValues[] = $lookup;
            $pattern = '(?:' . implode('|', $tokens) . ')';
            $expression = '$enumValues[' . $slot . '][self::token($s, $p)]';
        } elseif ($pattern === null) {
            $names = $field['scalarNames'] ?? [];
            if (
                $names === []
                || array_diff($names, ['string', 'int', 'float', 'bool', 'true', 'false', 'null']) !== []
            ) {
                return false;
            }
            $patterns = [];
            foreach ($names as $name) {
                $scalar = $this->scalar(['kind' => $name, 'nullable' => false, 'enumCases' => null]);
                if ($scalar === false) {
                    return false;
                }
                $patterns[] = $scalar[0];
            }
            $pattern = '(?:' . implode('|', $patterns) . ')';
            $expression =
                'self::scalarValue($s, $p, '
                . (in_array('float', $names, true) && !in_array('int', $names, true) ? 'true' : 'false')
                . ')';
        } else {
            $expression = match ($kind) {
                'string' => 'self::string($s, $p)',
                'int' => '(self::token($s, $p) + 0)',
                'float' => 'self::number($s, $p)',
                'bool', 'true', 'false' => 'self::boolean($s, $p)',
                default => 'self::null($p)',
            };
        }
        if ($field['nullable'] && $kind !== 'null') {
            $pattern = '(?:null|' . $pattern . ')';
            $expression = '($s[$p] === "n" ? self::null($p) : ' . $expression . ')';
        }
        return [$pattern, $expression];
    }

    public static function integerPattern(): string
    {
        $range = static function (string $maximum): string {
            $parts = ['[1-9][0-9]{0,' . (strlen($maximum) - 2) . '}'];
            for ($index = 0; $index < strlen($maximum); ++$index) {
                $minimum = $index === 0 ? 1 : 0;
                $digit = (int) $maximum[$index];
                if ($digit > $minimum) {
                    $parts[] =
                        substr($maximum, 0, $index)
                        . '['
                        . $minimum
                        . '-'
                        . ($digit - 1)
                        . '][0-9]{'
                        . (strlen($maximum) - $index - 1)
                        . '}';
                }
            }
            $parts[] = $maximum;
            return '(?:' . implode('|', $parts) . ')';
        };
        return '(?:-?0|' . $range((string) PHP_INT_MAX) . '|-' . $range(substr((string) PHP_INT_MIN, 1)) . ')';
    }

    // @mago-expect analysis:unused-method Invoked by generated cursor closures.
    private static function token(string $s, int &$p): string
    {
        $start = $p;
        if ($s[$p] === '"') {
            ++$p;
            do {
                $p += strcspn($s, "\"\\", $p);
                $char = $s[$p++];
                if ($char === '\\') {
                    ++$p;
                }
            } while ($char !== '"');
        } else {
            $p += strcspn($s, ",]} \t\r\n", $p);
        }
        return substr($s, $start, $p - $start);
    }

    /** @psalm-suppress UnusedMethod Called by generated cursor closures.
     * @mago-expect analysis:unused-method Invoked by generated cursor closures.
     * @phpstan-ignore method.unused (Called by generated cursor closures.)
     */
    private static function skip(string $s, int &$p): void
    {
        if ($s[$p] === 'n') {
            $p += 4;
            return;
        }
        if (!in_array($s[$p], ['{', '[', '"'], true)) {
            $p += strcspn($s, ",]} \t\r\n", $p);
            return;
        }
        $depth = 0;
        do {
            $p += strcspn($s, '[]{}"', $p);
            $char = $s[$p++];
            if ($char === '"') {
                do {
                    $p += strcspn($s, "\"\\", $p);
                    $char = $s[$p++];
                    if ($char === '\\') {
                        ++$p;
                    }
                } while ($char !== '"');
            } else {
                $depth += $char === '{' || $char === '[' ? 1 : -1;
            }
        } while ($depth > 0);
    }

    // @mago-expect analysis:unused-method Invoked by generated cursor closures.
    private static function string(string $s, int &$p): string
    {
        $start = $p + 1;
        $end = $start + strcspn($s, "\"\\", $start);
        if ($s[$end] === '"') {
            $p = $end + 1;
            return substr($s, $start, $end - $start);
        }
        $token = self::token($s, $p);
        /** @var string $value The typed grammar already validated this string token. */
        $value = json_decode($token);
        return $value;
    }

    private function whitePattern(): string
    {
        return $this->compact ? '' : self::WS;
    }

    private static function needsReflection(\ReflectionProperty $property): bool
    {
        return $property->isReadOnly() || $property->isPrivateSet() || $property->isProtectedSet();
    }

    /**
     * @param array{type: string|ListType|MapType|TupleType|CollectionUnionType|null, kind: string, nullable: bool, ...} $field
     */
    private function assignment(string $local, string $expression, array $field, string|null $delimiter): string
    {
        if ($field['type'] !== null || !in_array($field['kind'], ['string', 'int', 'float', 'bool'], true)) {
            return (
                $local
                . '='
                . $expression
                . ';'
                . (
                    $field['type'] === null
                        ? ''
                        : 'if (' . $local . ' instanceof \\Eventjet\\Json\\DecodeError) { return ' . $local . '; }'
                )
            );
        }
        $end = $delimiter !== null && $this->compact
            ? 'strpos($s, ' . var_export($delimiter, true) . ', $p)'
            : '$p + strcspn($s, ",]} \t\r\n", $p)';
        $code = match ($field['kind']) {
            'string' => '$start = $p + 1; $end = strpos($s, \'"\', $start);'
                . $local
                . '=substr($s, $start, $end - $start);'
                . 'if (str_contains('
                . $local
                . ', "\\\\")) {'
                . $local
                . '=self::string($s, $p); } else { $p = $end + 1; }',
            'int' => '$end = ' . $end . ';' . $local . '=(int) substr($s, $p, $end - $p); $p = $end;',
            'float' => '$end = '
                . $end
                . '; $token = substr($s, $p, $end - $p);'
                . $local
                . '=$token === "-0" ? 0.0 : (float) $token; $p = $end;',
            'bool' => $local . '=$s[$p] === "t"; $p += ' . $local . ' ? 4 : 5;',
        };
        return $field['nullable'] ? 'if ($s[$p] === "n") {' . $local . '=null; $p += 4; } else {' . $code . '}' : $code;
    }

    private function whiteCode(): string
    {
        if ($this->compact) {
            return '';
        }
        return '$p += strspn($s, " \t\r\n", $p);';
    }

    // @mago-expect analysis:unused-method Invoked by generated cursor closures.
    private static function number(string $s, int &$p): float
    {
        /** @var numeric-string $number The typed grammar validated this number. */
        $number = self::token($s, $p);
        return $number === '-0' ? 0.0 : (float) $number;
    }

    /**
     * @psalm-suppress UnusedMethod Called by generated cursor closures.
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @mago-expect analysis:unused-method Invoked by generated cursor closures.
     * @phpstan-ignore method.unused (Called by generated cursor closures.)
     */
    private static function scalarValue(string $s, int &$p, bool $float): array|bool|float|int|object|string|null
    {
        /** @var array<array-key, mixed>|bool|float|int|object|string|null $value */
        $value = match ($s[$p]) {
            '"' => self::string($s, $p),
            'n' => self::null($p),
            't', 'f' => self::boolean($s, $p),
            default => $float ? self::number($s, $p) : json_decode(self::token($s, $p)),
        };
        return $value;
    }

    // @mago-expect analysis:unused-method Invoked by generated cursor closures.
    private static function boolean(string $s, int &$p): bool
    {
        $value = $s[$p] === 't';
        $p += $value ? 4 : 5;
        return $value;
    }

    // @mago-expect analysis:unused-method Invoked by generated cursor closures.
    private static function null(int &$p): null
    {
        $p += 4;
        return null;
    }

    /**
     * @param array<int, Closure(string, int): (array<array-key, mixed>|bool|float|int|object|string|null)> $functions
     * @param list<\ReflectionProperty> $properties
     * @param list<array<int|string, \BackedEnum>> $enumValues
     * @return Closure(string, int): (array<array-key, mixed>|bool|float|int|object|string|null)
     * @mago-expect analysis:unused-parameter The emitted closure captures properties and enumValues by name.
     */
    private static function generatedNode(
        string $body,
        array &$functions,
        array $properties,
        array $enumValues,
    ): Closure {
        /** @var Closure(string, int): (array<array-key, mixed>|bool|float|int|object|string|null) $function Schema-derived code is emitted only after declaration validation. */
        $function = eval('return static function(string $s, int &$p) use (&$functions, $properties, $enumValues) { '
            . $body
            . ' };');
        return $function;
    }

    /**
     * @param array<int, Closure(string, int): (array<array-key, mixed>|bool|float|int|object|string|null)> $functions
     * @param list<\ReflectionProperty> $properties
     * @param list<array<int|string, \BackedEnum>> $enumValues
     * @return Closure(string): (array<array-key, mixed>|bool|float|int|object|string|null)
     * @mago-expect analysis:unused-parameter The emitted closure captures properties and enumValues by name.
     */
    private static function generatedRoot(
        string $expression,
        string $whitespace,
        array &$functions,
        array $properties,
        array $enumValues,
    ): Closure {
        /** @var Closure(string): (array<array-key, mixed>|bool|float|int|object|string|null) $function Schema-derived code is emitted only after declaration validation. */
        $function = eval('return static function(string $s) use (&$functions, $properties, $enumValues) { $p = 0; '
            . $whitespace
            . ' return '
            . $expression
            . '; };');
        return $function;
    }
}
