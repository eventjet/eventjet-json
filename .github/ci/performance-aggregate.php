<?php

declare(strict_types=1);

use Eventjet\Json\Ci\FpmMeasurements;
use Eventjet\Json\Ci\PerformanceShards;

require __DIR__ . '/PerformanceShards.php';
require __DIR__ . '/performance-summary.php';

$options = getopt('', ['artifacts:', 'opcache:', 'output:']);
if (
    $options === false
    || !is_string($options['artifacts'] ?? null)
    || !is_string($options['output'] ?? null)
    || !in_array($options['opcache'] ?? null, ['on', 'off'], true)
) {
    throw new InvalidArgumentException(
        'Usage: performance-aggregate.php --artifacts DIR --opcache on|off --output DIR',
    );
}
$comparison = PerformanceShards::collect($options['artifacts'], $options['opcache']);
$comment = performanceSummary($comparison['comparisons'], $comparison['status'], $options['opcache'], $comparison['fpm']);
if (!is_dir($options['output'])) {
    mkdir($options['output'], 0777, true);
}
file_put_contents($options['output'] . '/comment.md', $comment);
$summary = $comment . FpmMeasurements::report($comparison['fpm'], $options['opcache'] === 'on');
file_put_contents($options['output'] . '/summary.md', $summary);
$stepSummary = getenv('GITHUB_STEP_SUMMARY');
if ($stepSummary !== false && $stepSummary !== '') {
    file_put_contents($stepSummary, $summary, FILE_APPEND);
}
echo $summary;
exit(performanceStatus($comparison['status'], $options['opcache'], $comparison['fpm']));
