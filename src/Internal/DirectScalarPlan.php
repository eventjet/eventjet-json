<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal A fully validated scalar object, without an intermediate object tree. */
final readonly class DirectScalarPlan
{
    /** @var non-empty-string */
    private string $rootPattern;
    /** @var non-empty-string */
    private string $itemPattern;

    /**
     * @param class-string $class
     * @param non-empty-string $fragment
     * @param list<'string'|'int'|'float'|'bool'|'true'|'false'|'null'> $types
     */
    public function __construct(
        private string $class,
        public string $fragment,
        private array $types,
    ) {
        $this->rootPattern = '~\\A' . DirectJsonParser::WS . $fragment . DirectJsonParser::WS . '\\z~';
        $this->itemPattern = '~\\G' . $fragment . DirectJsonParser::WS . '(?:,' . DirectJsonParser::WS . ')?~';
    }

    public function decode(string $json): object|false
    {
        $matched = preg_match($this->rootPattern, $json);
        if ($matched !== 1) {
            return false;
        }
        // Native scalar conversion avoids a PHP loop and resets the JSON error state.
        /** @var array<string, string|int|float|bool|null> $values The complete scalar schema has already matched. */
        $values = json_decode($json, associative: true);
        /**
         * @psalm-suppress MixedMethodCall The validated class-string supplies the constructor.
         * @mago-expect analysis:unknown-class-instantiation A validated class-string supplies the constructor.
         */
        return new $this->class(...$values);
    }

    /** Read one record after the complete list grammar has been validated. */
    public function read(string $json, int &$position): object
    {
        $matches = [];
        /** @mago-expect lint:literal-named-argument Positional PCRE flags preserve Psalm's overloaded function contract. */
        $matched = preg_match($this->itemPattern, $json, $matches, 0, $position);
        /** @mago-expect lint:literal-named-argument Positional assertion arguments preserve Psalm's builtin contract. */
        assert($matched === 1, 'The complete list grammar has already validated this record.');
        /** @var array{0: string} $fullMatch A successful match always includes its full text. */
        $fullMatch = $matches;
        $position += strlen($fullMatch[0]);
        return $this->construct($matches);
    }

    /** @param array<array-key, string> $matches */
    private function construct(array $matches): object
    {
        $values = [];
        foreach ($this->types as $index => $kind) {
            /**
             * @psalm-suppress UnnecessaryVarAnnotation Mago needs the validated capture's string type.
             * @var string $raw Each schema field contributes exactly one capture.
             * @mago-expect analysis:possibly-undefined-int-array-index The complete grammar has matched every capture.
             */
            $raw = $matches[$index + 1];
            $values[] = match ($kind) {
                'null' => null,
                'string' => $raw === 'null' ? null : substr($raw, offset: 1, length: -1),
                'int' => $raw === 'null' ? null : (int) $raw,
                /**
                 * @mago-expect lint:no-nested-ternary The three JSON spellings preserve native null and signed-zero behavior.
                 * @mago-expect analysis:invalid-type-cast The complete numeric grammar validates this capture before conversion.
                 */
                'float' => $raw === 'null' ? null : ($raw === '-0' ? 0.0 : (float) $raw),
                'bool', 'true', 'false' => $raw === 'null' ? null : $raw === 'true',
            };
        }
        /**
         * @psalm-suppress MixedMethodCall The validated class-string supplies the constructor.
         * @mago-expect analysis:unknown-class-instantiation A validated class-string supplies the constructor.
         */
        return new $this->class(...$values);
    }
}
