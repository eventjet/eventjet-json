<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function json_encode;
use function json_validate;
use function preg_match;
use function preg_quote;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const PREG_OFFSET_CAPTURE;

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
        // Repeated groups retain only their final captures, independent of list size.
        $record = $item->fragment;
        $records = $record . '(?:' . DirectJsonParser::WS . ',' . DirectJsonParser::WS . $record . ')*+';
        $ws = DirectJsonParser::WS;
        $key = preg_quote(json_encode($field, JSON_THROW_ON_ERROR), delimiter: '~');
        $optional = $nonEmpty ? '' : '?';
        $this->pattern = "~\\A{$ws}\\{{$ws}{$key}{$ws}:{$ws}\\[{$ws}(?<records>(?:{$records}){$optional}){$ws}\\]{$ws}\\}{$ws}\\z~";
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
