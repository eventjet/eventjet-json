<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal Validate a complete record list before calling any constructor. */
final readonly class DirectListPlan
{
    /** @var non-empty-string */
    private string $pattern;

    /**
     * @param class-string $class
     * @throws \JsonException
     */
    public function __construct(
        private string $class,
        string $field,
        private DirectScalarPlan $item,
        bool $nonEmpty,
    ) {
        // Captures belong only to individual reads, keeping peak allocation bounded.
        $record = preg_replace('~\((?!\?)~', replacement: '(?:', subject: $item->fragment);
        assert($record !== null, description: 'The fixed capture-removal expression is valid.');
        $records = $record . '(?:' . DirectJsonParser::WS . ',' . DirectJsonParser::WS . $record . ')*+';
        $this->pattern =
            '~\\A'
            . DirectJsonParser::WS
            . '\\{'
            . DirectJsonParser::WS
            . preg_quote(json_encode($field, JSON_THROW_ON_ERROR), delimiter: '~')
            . DirectJsonParser::WS
            . ':'
            . DirectJsonParser::WS
            . '\\['
            . DirectJsonParser::WS
            . '(?<records>(?:'
            . $records
            . ')'
            . ($nonEmpty ? '' : '?')
            . ')'
            . DirectJsonParser::WS
            . '\\]'
            . DirectJsonParser::WS
            . '\\}'
            . DirectJsonParser::WS
            . '\\z~';
    }

    public function decode(string $json): object|false
    {
        $matches = [];
        $matched = preg_match($this->pattern, $json, $matches, PREG_OFFSET_CAPTURE);
        if ($matched !== 1) {
            return false;
        }
        /**
         * @psalm-suppress UnusedFunctionCall Constructors observe the native JSON error state.
         * @mago-expect analysis:unused-statement Constructors observe the native JSON error state.
         */
        json_validate('null');
        /**
         * @psalm-suppress UnnecessaryVarAnnotation Mago does not infer PREG_OFFSET_CAPTURE's tuple values.
         * @var array{records: array{string, int<-1, max>}} $matches The grammar captures list text and its offset.
         */
        [$records, $position] = $matches['records'];
        $end = $position + strlen($records);
        $values = [];
        while ($position < $end) {
            $values[] = $this->item->read($json, $position);
        }
        /**
         * @psalm-suppress MixedMethodCall The validated class-string supplies the constructor.
         * @mago-expect analysis:unknown-class-instantiation A validated class-string supplies the constructor.
         */
        return new $this->class($values);
    }
}
