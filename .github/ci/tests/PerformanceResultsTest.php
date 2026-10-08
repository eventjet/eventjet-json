<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci\Test;

use Eventjet\Json\Ci\PerformanceResults;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../PerformanceResults.php';

#[CoversNothing]
final class PerformanceResultsTest extends TestCase
{
    private const string XML =
        '<phpbench><suite><benchmark class="Decoder"><subject name="warm">'
            . '<variant revs="10"><parameter-set name="objects"/>'
            . '<iteration time-net="100" mem-peak="1024"/><iteration time-net="120" mem-peak="2048"/>'
            . '</variant></subject></benchmark></suite></phpbench>';

    public function testNormalizesIterationsAndSummarizesPairs(): void
    {
        $baseline = PerformanceResults::samples(self::XML);
        $key = 'Decoder / warm / objects';
        self::assertSame(['time_us' => [10.0, 12.0], 'peak_bytes' => 2048], $baseline[$key]);
        $candidate = [$key => ['time_us' => [12.5, 15.0], 'peak_bytes' => 4096]];
        $result = PerformanceResults::summarize(array_fill(0, 3, [$baseline, $candidate]))[$key];
        self::assertSame(11.0, $result['baseline_us']);
        self::assertSame(13.75, $result['candidate_us']);
        self::assertSame(25.0, $result['change_percent']);
        self::assertSame([25.0, 25.0, 25.0], $result['paired_changes_percent']);
        self::assertSame(2048, $result['baseline_peak_bytes']);
        self::assertSame(4096, $result['candidate_peak_bytes']);
        self::assertEqualsWithDelta(9.9585919546, $result['baseline_cv_percent'], 0.000001);
        self::assertEqualsWithDelta($result['baseline_cv_percent'], $result['candidate_cv_percent'], 0.000001);
        self::assertSame(0.0, PerformanceResults::summarize([[$baseline, $baseline]])[$key]['change_percent']);
    }
}
