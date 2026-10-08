<?php

declare(strict_types=1);

/** @param non-empty-list<string> $paths */
function performanceSummary(array $paths, int $status): string
{
    $rows = [];
    $slower = 0;
    $faster = 0;
    $logRatios = 0.0;
    foreach ($paths as $path) {
        $xml = simplexml_load_file($path);
        if ($xml === false) {
            throw new RuntimeException('Cannot read comparison results');
        }
        foreach ($xml->suite->benchmark as $benchmark) {
            foreach ($benchmark->subject as $subject) {
                foreach ($subject->variant as $variant) {
                    $base = (float) $variant->{'baseline-stats'}['mode'];
                    $candidate = (float) $variant->stats['mode'];
                    if ($base <= 0 || $candidate <= 0 || !is_finite($base) || !is_finite($candidate)) {
                        throw new RuntimeException('Comparison requires positive, finite mode times');
                    }
                    $ratio = $candidate / $base;
                    $logRatios += log($ratio);
                    $slower += (int) ($candidate > ($base * 1.05));
                    $faster += (int) ($candidate < ($base * 0.95));
                    $name =
                        $benchmark['class']
                        . '::'
                        . $subject['name']
                        . ' ['
                        . $variant->{'parameter-set'}['name']
                        . ']';
                    $rows[] = [
                        'name' => htmlspecialchars(
                            str_replace(["\r", "\n", '|', '`', '@'], [' ', ' ', '&#124;', '&#96;', '&#64;'], $name),
                            ENT_NOQUOTES,
                            double_encode: false,
                        ),
                        'base' => $base,
                        'candidate' => $candidate,
                        'change' => ($ratio - 1) * 100,
                    ];
                }
            }
        }
    }
    if ($rows === []) {
        throw new RuntimeException('No comparable workload measurements');
    }
    usort($rows, static fn(array $a, array $b): int => $a['change'] <=> $b['change']);
    $verdict = $status !== 0
        ? '🔴 Performance regression'
        : ($faster > 0 ? '🟢 Performance improvement' : '⚪ No significant performance changes');
    $overall = (exp($logRatios / count($rows)) - 1) * 100;
    $text =
        '## '
        . $verdict
        . "\n\n"
        . sprintf("**Overall decode time: %+.2f%%** · **%d workloads**\n\n", $overall, count($rows))
        . sprintf(
            "| Faster (>5%%) | Within ±5%% | Slower (>5%%) |\n| ---: | ---: | ---: |\n| %d | %d | %d |\n\n",
            $faster,
            count($rows) - $faster - $slower,
            $slower,
        )
        . 'Overall is the geometric mean of candidate/target mode-time ratios, with equal weight per workload. Negative means faster. '
        . "Any workload more than 5% slower triggers a regression; otherwise, a workload more than 5% faster indicates an improvement. Changes within ±5% are treated as noise.\n\n"
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
    return $text;
}
