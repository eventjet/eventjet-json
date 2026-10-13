<?php

declare(strict_types=1);

use Eventjet\Json\Ci\BenchmarkComparison;
use Eventjet\Json\Ci\FpmComparison;
use Eventjet\Json\Ci\FpmMeasurements;

require_once __DIR__ . '/FpmMeasurements.php';

function summaryName(string $name): string
{
    return htmlspecialchars(
        str_replace(["\r", "\n", '|', '`', '@'], [' ', ' ', '&#124;', '&#96;', '&#64;'], $name),
        ENT_NOQUOTES,
        double_encode: false,
    );
}

function sectionResult(bool $blocks, bool $slower, bool $faster): string
{
    if ($slower) {
        return $blocks ? '🔴 Regression' : '🟡 Slower than the limit, advisory only';
    }
    return $faster ? '🟢 Improvement' : '⚪ No significant changes';
}

/** @param list<FpmComparison> $fpm */
function fpmSlower(array $fpm): bool
{
    foreach ($fpm as $comparison) {
        if ($comparison->slower()) {
            return true;
        }
    }
    return false;
}

/**
 * The CLI status is PHPBench's assertion status, which covers only blocking CLI workloads.
 *
 * @param list<FpmComparison>|null $fpm
 */
function performanceStatus(int $status, string $mode, array|null $fpm): int
{
    $fpmRegressed = $fpm !== null && FpmMeasurements::blocks($mode === 'on') && fpmSlower($fpm);
    return max($status, $fpmRegressed ? 2 : 0);
}

/**
 * Blocking sections take their verdict from PHPBench's assertion; advisory sections have no assertion.
 *
 * @param non-empty-list<BenchmarkComparison> $comparisons
 * @return array{text: string, faster: bool}
 */
function cliSection(string $title, array $comparisons, bool $blocks, bool $assertionFailed): array
{
    $rows = [];
    $slower = 0;
    $faster = 0;
    $logRatios = 0.0;
    foreach ($comparisons as $comparison) {
        $ratio = $comparison->candidate / $comparison->baseline;
        $logRatios += log($ratio);
        $slower += (int) ($comparison->candidate > ($comparison->baseline * 1.05));
        $faster += (int) ($comparison->candidate < ($comparison->baseline * 0.95));
        $workload = $comparison->workload;
        $rows[] = [
            'name' => summaryName($workload->class . '::' . $workload->subject . ' [' . $workload->parameter . ']'),
            'base' => $comparison->baseline,
            'candidate' => $comparison->candidate,
            'change' => ($ratio - 1) * 100,
        ];
    }
    usort($rows, static fn(array $a, array $b): int => $a['change'] <=> $b['change']);
    $text = '### ' . $title . ' · ' . ($blocks ? 'blocking' : 'advisory') . "\n\n"
        . sectionResult($blocks, $blocks ? $assertionFailed : $slower > 0, $faster > 0)
        . sprintf(
            " · **Overall decode time: %+.2f%%** · **%d workloads**\n\n",
            (exp($logRatios / count($rows)) - 1) * 100,
            count($rows),
        )
        . sprintf(
            "| Faster (>5%%) | Within ±5%% | Slower (>5%%) |\n| ---: | ---: | ---: |\n| %d | %d | %d |\n\n",
            $faster,
            count($rows) - $faster - $slower,
            $slower,
        )
        . "| Timing range | Workload | Target | PR | Change |\n| --- | --- | ---: | ---: | ---: |\n";
    foreach (['Lowest change' => $rows[0], 'Highest change' => $rows[count($rows) - 1]] as $label => $row) {
        $text .= sprintf(
            "| %s | %s | %.2f µs | %.2f µs | %+.2f%% |\n",
            $label,
            $row['name'],
            $row['base'],
            $row['candidate'],
            $row['change'],
        );
    }
    return ['text' => $text . "\n", 'faster' => $faster > 0];
}

/**
 * @param non-empty-list<FpmComparison> $fpm
 * @return array{text: string, faster: bool}
 */
function fpmSection(array $fpm, string $mode): array
{
    $blocks = FpmMeasurements::blocks($mode === 'on');
    $limit = FpmMeasurements::LIMIT_PERCENT;
    $faster = false;
    $rows = '';
    foreach ($fpm as $comparison) {
        $faster = $faster || $comparison->faster();
        $rows .= sprintf(
            "| %s | %.2f µs | %.2f µs | %+.2f%% |\n",
            summaryName($comparison->workload),
            $comparison->baseline,
            $comparison->candidate,
            $comparison->change(),
        );
    }
    $text = '### First decode in a fresh PHP-FPM request · ' . ($blocks ? 'blocking' : 'advisory') . "\n\n"
        . sectionResult($blocks, fpmSlower($fpm), $faster)
        . sprintf(
            " · Median of %d requests per revision · Limit ±%s%%\n\n",
            FpmMeasurements::rounds($mode === 'on') * FpmMeasurements::requests($mode === 'on'),
            rtrim(rtrim(sprintf('%.1f', $limit), '0'), '.'),
        )
        . "| Document | Target | PR | Change |\n| --- | ---: | ---: | ---: |\n"
        . $rows;
    return ['text' => $text . "\n", 'faster' => $faster];
}

/**
 * @param non-empty-list<BenchmarkComparison> $comparisons
 * @param non-empty-list<FpmComparison>|null $fpm
 */
function performanceSummary(array $comparisons, int $status, string $mode, array|null $fpm): string
{
    $warm = array_values(array_filter($comparisons, static fn(BenchmarkComparison $c): bool => $c->workload->blocks()));
    $cold = array_values(array_filter($comparisons, static fn(BenchmarkComparison $c): bool => !$c->workload->blocks()));
    $sections = [];
    $improved = false;
    if ($fpm !== null) {
        $section = fpmSection($fpm, $mode);
        $sections[] = $section['text'];
        $improved = FpmMeasurements::blocks($mode === 'on') && $section['faster'];
    } else {
        $sections[] = "### First decode in a fresh PHP-FPM request\n\nNot measured in this run.\n\n";
    }
    if ($warm !== []) {
        $section = cliSection('Warm CLI decodes', $warm, true, $status !== 0);
        $sections[] = $section['text'];
        $improved = $improved || $section['faster'];
    }
    if ($cold !== []) {
        $sections[] = cliSection('Fresh CLI processes', $cold, false, false)['text'];
    }
    $verdict = performanceStatus($status, $mode, $fpm) !== 0
        ? '🔴 Performance regression'
        : ($improved ? '🟢 Performance improvement' : '⚪ No significant performance changes');
    return '## ' . $verdict . ' (OPcache ' . $mode . ")\n\n"
        . "Only blocking sections decide this result. Advisory sections are shown for information.\n\n"
        . implode('', $sections)
        . 'CLI sections compare PHPBench mode times per decode; overall is the geometric mean of PR/target ratios, '
        . "with equal weight per workload. The PHP-FPM section compares median request times. Negative means faster.\n";
}
