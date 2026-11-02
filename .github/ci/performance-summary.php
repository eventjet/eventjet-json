<?php

declare(strict_types=1);

use Eventjet\Json\Ci\BenchmarkComparison;
use Eventjet\Json\Ci\BenchmarkWorkload;
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

function limitText(float $percent): string
{
    return rtrim(rtrim(sprintf('%.1f', $percent), '0'), '.') . '%';
}

/**
 * @param list<BenchmarkComparison> $comparisons
 * @param list<FpmComparison>|null $fpm
 */
function performanceStatus(array $comparisons, string $mode, array|null $fpm): int
{
    foreach ($comparisons as $comparison) {
        if ($comparison->workload->blocks() && $comparison->slower()) {
            return 2;
        }
    }
    return $fpm !== null && FpmMeasurements::blocks($mode === 'on') && fpmSlower($fpm) ? 2 : 0;
}

/**
 * @param non-empty-list<BenchmarkComparison> $comparisons
 * @return array{text: string, faster: bool}
 */
function cliSection(string $title, array $comparisons, bool $blocks): array
{
    $rows = [];
    $slower = 0;
    $faster = 0;
    $logRatios = 0.0;
    foreach ($comparisons as $comparison) {
        $ratio = $comparison->candidate / $comparison->baseline;
        $logRatios += log($ratio);
        $slower += (int) $comparison->slower();
        $faster += (int) $comparison->faster();
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
        . sectionResult($blocks, $slower > 0, $faster > 0)
        . sprintf(
            " · **Overall decode time: %+.2f%%** · **%d workloads** · Limit ±%s\n\n",
            (exp($logRatios / count($rows)) - 1) * 100,
            count($rows),
            limitText(BenchmarkComparison::LIMIT_PERCENT),
        )
        . sprintf(
            "| Faster (>%3\$s) | Within ±%3\$s | Slower (>%3\$s) |\n| ---: | ---: | ---: |\n| %1\$d | %2\$d | %4\$d |\n\n",
            $faster,
            count($rows) - $faster - $slower,
            limitText(BenchmarkComparison::LIMIT_PERCENT),
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
            " · Median of %d paired rounds, %d requests per revision · Limit ±%s\n\n",
            FpmMeasurements::rounds($mode === 'on'),
            FpmMeasurements::rounds($mode === 'on') * FpmMeasurements::REQUESTS,
            limitText(FpmMeasurements::LIMIT_PERCENT),
        )
        . "| Document | Target | PR | Change |\n| --- | ---: | ---: | ---: |\n"
        . $rows;
    return ['text' => $text . "\n", 'faster' => $faster];
}

/**
 * @param non-empty-list<BenchmarkComparison> $comparisons
 * @param non-empty-list<FpmComparison>|null $fpm
 */
function performanceSummary(array $comparisons, string $mode, array|null $fpm): string
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
        $section = cliSection('Warm CLI decodes', $warm, true);
        $sections[] = $section['text'];
        $improved = $improved || $section['faster'];
    }
    if ($cold !== []) {
        $sections[] = cliSection('Fresh CLI processes', $cold, false)['text'];
    }
    $verdict = performanceStatus($comparisons, $mode, $fpm) !== 0
        ? '🔴 Performance regression'
        : ($improved ? '🟢 Performance improvement' : '⚪ No significant performance changes');
    return '## ' . $verdict . ' (OPcache ' . $mode . ")\n\n"
        . "Only blocking sections decide this result. Advisory sections are shown for information.\n\n"
        . implode('', $sections)
        . 'CLI sections compare median times per decode over alternating rounds; overall is the geometric mean of '
        . "PR/target ratios, with equal weight per workload. The PHP-FPM change is the median of per-round PR/target ratios; "
        . "its Target and PR columns are median request times. Negative means faster.\n";
}

/** @param list<BenchmarkComparison> $comparisons */
function cliReport(array $comparisons): string
{
    $groups = [];
    foreach ($comparisons as $comparison) {
        $workload = $comparison->workload;
        $groups[$workload->category] = ($groups[$workload->category] ?? '') . sprintf(
            "| %s | %s | %.3f µs | %.3f µs | %+.2f%% |\n",
            summaryName($workload->class . '::' . $workload->subject . ' [' . $workload->parameter . ']'),
            $workload->blocks() ? 'blocking' : 'advisory',
            $comparison->baseline,
            $comparison->candidate,
            $comparison->change(),
        );
    }
    $text = sprintf(
        "\n## CLI workloads\n\n%d alternating rounds per revision, each with %d PHPBench iterations in separate processes. Times are medians per decode.\n",
        BenchmarkComparison::ROUNDS,
        BenchmarkComparison::ITERATIONS,
    );
    foreach (BenchmarkWorkload::REPORT_GROUPS as $group => $title) {
        if (isset($groups[$group])) {
            $text .= "\n### $title\n\n| Workload | Gate | Target | PR | Change |\n| --- | --- | ---: | ---: | ---: |\n" . $groups[$group];
        }
    }
    return $text;
}
