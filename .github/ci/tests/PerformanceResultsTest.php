<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci\Test;

use Eventjet\Json\Ci\PerformanceGate;
use Eventjet\Json\Ci\PerformanceResults;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../PerformanceResults.php';
require_once __DIR__ . '/../PerformanceGate.php';

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

    public function testUsesMedianOfPairedChanges(): void
    {
        $sample = static fn(float $time): array => ['case' => ['time_us' => [$time, $time], 'peak_bytes' => 1024]];
        $result = PerformanceResults::summarize([
            [$sample(10), $sample(20)],
            [$sample(100), $sample(50)],
            [$sample(1000), $sample(1250)],
        ])['case'];
        self::assertSame([100.0, -50.0, 25.0], $result['paired_changes_percent']);
        self::assertSame(25.0, $result['change_percent']);
    }

    public function testRejectsMissingCases(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PerformanceResults::summarize([[PerformanceResults::samples(self::XML), []]]);
    }

    /**
     * @param list<float> $changes
     * @param 'pass'|'regression'|'inconclusive' $expected
     */
    #[DataProvider('gateCases')]
    public function testGateRequiresRepeatableMeaningfulChanges(
        array $changes,
        float $baselineTime,
        float $noise,
        bool $calibrated,
        string $expected,
    ): void {
        $sample = static fn(float $time): array => ['case' => ['time_us' => [$time, $time], 'peak_bytes' => 1024]];
        $baseline = $sample($baselineTime);
        $pairs = [];
        foreach ($changes as $change) {
            $pairs[] = [$baseline, $sample($baselineTime + (($baselineTime * $change) / 100))];
        }
        $comparison = PerformanceResults::summarize($pairs);
        $calibration = PerformanceResults::summarize(array_fill(0, 3, [
            $baseline,
            $sample($baselineTime + (($baselineTime * $noise) / 100)),
        ]));
        $thresholds = $calibrated ? ['case' => 10.0] : [];
        self::assertSame(['case' => $expected], PerformanceGate::evaluate($comparison, $calibration, $thresholds));
        self::assertStringContainsString('| ' . $expected . ' |', PerformanceGate::report([
            'case' => $expected,
        ], $thresholds));
    }

    /** @return iterable<string, array{list<float>, float, float, bool, string}> */
    public static function gateCases(): iterable
    {
        yield 'exact threshold' => [[10.0, 10.0, 10.0], 100.0, 0.0, true, 'pass'];
        yield 'exact noise limit' => [[0.0, 0.0, 0.0], 100.0, 5.0, true, 'pass'];
        yield 'unchanged' => [[0.0, 0.0, 0.0], 100.0, 0.0, true, 'pass'];
        yield 'improvement' => [[-20.0, -30.0, -25.0], 100.0, 0.0, true, 'pass'];
        yield 'below relative limit' => [[5.0, 5.0, 5.0], 100.0, 0.0, true, 'pass'];
        yield 'small absolute regression' => [[20.0, 30.0, 25.0], 1.0, 0.0, true, 'regression'];
        yield 'repeatable regression' => [[20.0, 30.0, 25.0], 100.0, 0.0, true, 'regression'];
        yield 'one slow pair' => [[20.0, 0.0, 0.0], 100.0, 0.0, true, 'inconclusive'];
        yield 'two slow pairs' => [[20.0, 30.0, 0.0], 100.0, 0.0, true, 'inconclusive'];
        yield 'noisy calibration' => [[20.0, 30.0, 25.0], 100.0, 6.0, true, 'inconclusive'];
        yield 'negative calibration noise' => [[0.0, 0.0, 0.0], 100.0, -6.0, true, 'inconclusive'];
        yield 'uncalibrated workload' => [[0.0, 0.0, 0.0], 100.0, 0.0, false, 'inconclusive'];
        yield 'missing pair' => [[20.0, 30.0], 100.0, 0.0, true, 'inconclusive'];
    }

    public function testParsesThresholds(): void
    {
        self::assertSame(['case' => 10.0], PerformanceGate::thresholds('{"case":10}'));
    }

    #[DataProvider('invalidThresholds')]
    public function testRejectsInvalidThresholds(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);
        PerformanceGate::thresholds($json);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidThresholds(): iterable
    {
        yield 'empty' => ['{}'];
        yield 'scalar' => ['true'];
        yield 'invalid entry' => ['{"case":[]}'];
        yield 'missing limit' => ['{"case":null}'];
        yield 'string limit' => ['{"case":"10"}'];
        yield 'zero limit' => ['{"case":0}'];
        yield 'negative limit' => ['{"case":-1}'];
        yield 'infinite limit' => ['{"case":1e999}'];
    }

    #[DataProvider('invalidOutput')]
    public function testRejectsInvalidOutput(string $xml): void
    {
        $this->expectException(InvalidArgumentException::class);
        PerformanceResults::samples($xml);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOutput(): iterable
    {
        yield 'empty' => ['<phpbench/>'];
        yield 'error' => ['<phpbench><error/></phpbench>'];
        yield 'failure' => ['<phpbench><failure/></phpbench>'];
        yield 'malformed' => ['<phpbench>'];
        yield 'zero revolutions' => [str_replace('revs="10"', 'revs="0"', self::XML)];
        yield 'zero time' => [str_replace('time-net="100"', 'time-net="0"', self::XML)];
        yield 'non-finite time' => [str_replace('time-net="100"', 'time-net="1e999"', self::XML)];
        yield 'missing memory' => [str_replace(' mem-peak="1024"', '', self::XML)];
        yield 'no iterations' => [str_replace(
            '<iteration time-net="100" mem-peak="1024"/><iteration time-net="120" mem-peak="2048"/>',
            '',
            self::XML,
        )];
        yield 'duplicate' => [str_replace(
            '</suite>',
            substr(self::XML, strlen('<phpbench><suite>'), -strlen('</suite></phpbench>')) . '</suite>',
            self::XML,
        )];
    }

    public function testReportIncludesNoiseMemoryAndChangedWorkloads(): void
    {
        $baseline = PerformanceResults::samples(self::XML);
        $comparison = PerformanceResults::summarize([[$baseline, $baseline]]);
        $text = PerformanceResults::report(
            ['baseline' => 'aaa', 'candidate' => 'bbb', 'workloads_changed' => true],
            $comparison,
            $comparison,
        );
        self::assertStringContainsString('Baseline: `aaa`. Candidate: `bbb`.', $text);
        self::assertStringContainsString('| Decoder / warm / objects | 11.00 | 11.00 | +0.0%', $text);
        self::assertStringContainsString('A/A max absolute change', $text);
        self::assertStringContainsString('Peak MiB base / candidate', $text);
        self::assertStringContainsString('candidate-workloads.xml', $text);
    }
}
