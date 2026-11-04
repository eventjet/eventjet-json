<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\AndFilter;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Equals;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\OrFilter;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Query;

use function count;
use function sprintf;

/** @internal */
final class FilterDocuments
{
    public static function query(Document $document): Query
    {
        return new Query(self::filter($document, Query::class, 'filter', 0));
    }

    private static function filter(
        Document $document,
        string $container,
        string $path,
        int $depth,
    ): AndFilter|OrFilter|Equals {
        $dice = $document->dice;
        $tag = Tag::of('type', "'and', 'or', 'equals'", 'not');
        $leaf = $dice->chance(40);
        if ($leaf || $depth >= 3) {
            return $document->tagged($container, $path, new Equals('equals', 'field-' . $depth, 'value'), $tag);
        }
        $and = $dice->chance(50);
        $class = $and ? AndFilter::class : OrFilter::class;
        $operands = [];
        $operandsPath = $path === '' ? 'operands' : $path . '.operands';
        for ($index = $dice->int(0, 3); $index > 0; --$index) {
            $operandPath = sprintf('%s[%d]', $operandsPath, count($operands));
            $operands[] = self::filter($document, $class, $operandPath, $depth + 1);
        }
        $filter = $class === AndFilter::class ? new AndFilter('and', $operands) : new OrFilter('or', $operands);
        return $document->tagged($container, $path, $filter, $tag);
    }
}
