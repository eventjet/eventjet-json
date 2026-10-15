<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Internal\PhpDocSimpleTags;
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
            $referenceTags[] = [$tag->name, $tag->variable, $tag->type->type];
        }
        $theirs = TypeDescription::tags($referenceTags);
        $differences = $ours === $theirs ? [] : ['decoder: ' . $ours, 'PHPStan: ' . $theirs];
        foreach ($reference as $tag) {
            $differences = [...$differences, ...self::numbers($tag->type, $report)];
        }
        $report->compare($doc, $differences);
    }

    /** Checks that the decoder parses a standalone type like PHPStan. */
    public static function type(string $source, ParityReport $report): void
    {
        $ours = TypeDescription::type(PhpDocTypeParser::parse($source));
        $reference = PhpStanReader::type($source);
        $theirs = TypeDescription::type($reference->type);
        $differences = $ours === $theirs ? [] : ['decoder: ' . $ours, 'PHPStan: ' . $theirs];
        $report->compare($source, [...$differences, ...self::numbers($reference, $report)]);
    }

    /** Checks that the decoder selects the same type for a field as PHPStan. */
    public static function field(
        string $label,
        PhpDocType|null $ours,
        PhpStanType|null $reference,
        ParityReport $report,
    ): void {
        $decoder = TypeDescription::type($ours);
        $phpstan = TypeDescription::type($reference?->type);
        $report->compare($label, $decoder === $phpstan ? [] : ['decoder: ' . $decoder, 'PHPStan: ' . $phpstan]);
    }

    /** @return list<string> */
    private static function numbers(PhpStanType $reference, ParityReport $report): array
    {
        if ($reference->type === null) {
            return [];
        }
        [$differences, $rejected] = NumberLiterals::differences($reference->type, $reference->numbers);
        $report->count('number literals', count($reference->numbers));
        $report->count('deliberately rejected number literals', $rejected);
        return $differences;
    }
}
