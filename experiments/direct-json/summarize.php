<?php

declare(strict_types=1);

// Convert saved benchmark profiles to a compact, reviewable CSV. Raw JSON keeps
// every sample; the CSV includes dispersion instead of presenting medians alone.
$output = fopen('php://stdout', 'w');
fputcsv(
    $output,
    [
        'profile',
        'php',
        'jit',
        'scenario',
        'mode',
        'input_bytes',
        'median_us',
        'min_us',
        'max_us',
        'cold_us',
        'peak_bytes',
        'cold_peak_bytes',
        'retained_bytes',
    ],
    escape: '',
);
foreach (array_slice($argv, 1) as $path) {
    $profile = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    foreach ($profile['rows'] as $row) {
        $times = array_column($row['samples'], 'warm_us');
        fputcsv(
            $output,
            [
                basename($path),
                $profile['php'],
                $profile['jit'],
                $row['scenario'],
                $row['mode'],
                $row['bytes'],
                round($row['warm_us'], 3),
                round(min($times), 3),
                round(max($times), 3),
                round($row['cold_us'], 3),
                $row['peak_bytes'],
                $row['cold_peak_bytes'],
                $row['retained_bytes'],
            ],
            escape: '',
        );
    }
}
