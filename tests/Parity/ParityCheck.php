<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\PhpDocSimpleTags;
use Eventjet\Json\Internal\PhpDocTag;
use Eventjet\Json\Internal\PhpDocTagParser;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Test\Parity\Reference\PhpStanReader;
use Eventjet\Json\Test\Parity\Reference\PhpStanType;

use function count;

/**
 * Compares the decoder's PHPDoc reader with its fast path and with phpstan/phpdoc-parser.
 *
 * @internal
 */
final class ParityCheck
{
    /** Checks that the fast path, where it reads a doc comment, reads the same tags as the full parser. */
    public static function fastPath(string $doc, ParityReport $report): void
    {
        $simple = PhpDocSimpleTags::tags($doc);
        if ($simple === null) {
            $report->count('left to the full parser');
            return;
        }
        $simpleTags = TypeDescription::tags($simple);
        $fullTags = TypeDescription::tags(PhpDocTagParser::tags($doc));
        $report->compare(
            $doc,
            $simpleTags === $fullTags ? [] : ['fast path: ' . $simpleTags, 'full parser: ' . $fullTags],
        );
    }

    /**
     * Checks that the decoder reads the same tags with the same types as PHPStan, and reads their number literals like
     * PHPStan apart from the deliberate rejections.
     */
    public static function docComment(string $doc, ParityReport $report): void
    {
        $simple = PhpDocSimpleTags::tags($doc);
        $report->count($simple === null ? 'read by the full parser' : 'read by the fast path');
        $tags = $simple ?? PhpDocTagParser::tags($doc);
        $reference = PhpStanReader::tags($doc);
        $report->count($reference === [] ? 'without tags' : 'with tags');
        $ours = TypeDescription::tags($tags);
        $referenceTags = [];
        foreach ($reference as $tag) {
            $referenceTags[] = new PhpDocTag($tag->name, $tag->variable, $tag->type->type);
        }
        $theirs = TypeDescription::tags($referenceTags);
        $differences = $ours === $theirs ? [] : ['decoder: ' . $ours, 'PHPStan: ' . $theirs];
        foreach ($reference as $index => $tag) {
            $unsupported = ($tags[$index] ?? null)?->type?->unsupportedNumber;
            $differences = [...$differences, ...self::numbers($unsupported, $tag->type, $report)];
        }
        $report->compare($doc, $differences);
    }

    /** Checks that the decoder parses a standalone type like PHPStan. */
    public static function type(string $source, ParityReport $report): void
    {
        $type = PhpDocTypeParser::parse($source);
        $ours = TypeDescription::type($type);
        $reference = PhpStanReader::type($source);
        $theirs = TypeDescription::type($reference->type);
        $differences = $ours === $theirs ? [] : ['decoder: ' . $ours, 'PHPStan: ' . $theirs];
        $numbers = self::numbers($type?->unsupportedNumber, $reference, $report);
        $report->compare($source, [...$differences, ...$numbers]);
    }

    /**
     * Checks that the decoder selects the same type for a field as PHPStan, or rejects it for a number literal that it
     * deliberately rejects.
     */
    public static function field(
        string $label,
        PhpDocType|DecodeError|null $ours,
        PhpStanType|null $reference,
        ParityReport $report,
    ): void {
        $rejected = NumberLiterals::rejectsAny($reference->numbers ?? []);
        $decoder = $ours instanceof DecodeError ? 'rejected' : TypeDescription::type($ours);
        $phpstan = $rejected ? 'rejected' : TypeDescription::type($reference?->type);
        $report->compare($label, $decoder === $phpstan ? [] : ['decoder: ' . $decoder, 'PHPStan: ' . $phpstan]);
    }

    /** @return list<string> */
    private static function numbers(string|null $unsupported, PhpStanType $reference, ParityReport $report): array
    {
        if ($reference->type === null) {
            return [];
        }
        [$differences, $rejected] = NumberLiterals::differences($unsupported, $reference->numbers);
        $report->count('number literals', count($reference->numbers));
        $report->count('deliberately rejected number literals', $rejected);
        return $differences;
    }
}
