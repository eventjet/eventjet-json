<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;
use SimpleXMLElement;

require_once __DIR__ . '/BenchmarkWorkload.php';
require_once __DIR__ . '/BenchmarkComparison.php';
require_once __DIR__ . '/Statistics.php';

final readonly class BenchmarkReport
{
    /**
     * @param non-empty-list<BenchmarkWorkload> $workloads
     * @param non-empty-list<SimpleXMLElement> $variants
     */
    private function __construct(
        private SimpleXMLElement $xml,
        public array $workloads,
        private array $variants,
    ) {}

    public static function read(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException('Missing measurement: ' . $path);
        }
        $xml = simplexml_load_file($path);
        if ($xml === false) {
            throw new RuntimeException('Cannot read measurement: ' . $path);
        }
        $workloads = [];
        $variants = [];
        foreach ($xml->suite as $suite) {
            foreach ($suite->benchmark as $benchmark) {
                foreach ($benchmark->subject as $subject) {
                    $categories = [];
                    $cold = false;
                    foreach ($subject->group as $group) {
                        $name = (string) $group['name'];
                        $cold = $cold || $name === 'cold';
                        if (isset(BenchmarkWorkload::REPORT_GROUPS[$name])) {
                            $categories[$name] = true;
                        }
                    }
                    if (count($categories) > 1) {
                        throw new RuntimeException('A benchmark subject must belong to only one report category');
                    }
                    foreach ($subject->variant as $variant) {
                        $workloads[] = new BenchmarkWorkload(
                            (string) $benchmark['class'],
                            (string) $subject['name'],
                            (string) $variant->{'parameter-set'}['name'],
                            array_key_first($categories) ?? 'uncategorized',
                            $cold,
                        );
                        $variants[] = $variant;
                    }
                }
            }
        }
        if ($workloads === [] || $variants === []) {
            throw new RuntimeException('No benchmark workloads discovered');
        }
        $report = new self($xml, $workloads, $variants);
        if (count(array_unique($report->identities())) !== count($workloads)) {
            throw new RuntimeException('Workloads must be unique');
        }
        return $report;
    }

    /** @return non-empty-list<string> */
    public function identities(): array
    {
        return array_map(static fn(BenchmarkWorkload $workload): string => $workload->identity(), $this->workloads);
    }

    public function requireMode(string $mode): void
    {
        $opcache = $this->xml->xpath('//env/opcache/value[@name="enabled"]');
        if (
            $opcache === null
            || count($opcache) !== 1
            || !in_array((string) $opcache[0], $mode === 'on' ? ['1'] : ['', '0'], true)
        ) {
            throw new RuntimeException('Benchmark OPcache state does not match the requested mode');
        }
    }

    /** @return array{times: non-empty-list<float>, revs: string, warmup: string} */
    private function sample(string $identity, string $mode): array
    {
        if ($this->identities() !== [$identity]) {
            throw new RuntimeException('Measured workload differs from the assigned workload');
        }
        $this->requireMode($mode);
        $variant = $this->variants[0];
        if (count($variant->iteration) !== BenchmarkComparison::ITERATIONS) {
            throw new RuntimeException('Measurement sampling differs');
        }
        $times = [];
        foreach ($variant->iteration as $iteration) {
            $time = (float) $iteration['time-avg'];
            if ($time <= 0 || !is_finite($time)) {
                throw new RuntimeException('Invalid iteration time');
            }
            $times[] = $time;
        }
        if ($times === []) {
            throw new RuntimeException('Missing iterations');
        }
        return ['times' => $times, 'revs' => (string) $variant['revs'], 'warmup' => (string) $variant['warmup']];
    }

    /**
     * Compares the medians of all rounds' iteration times.
     *
     * @param list<self> $baselines
     * @param list<self> $candidates
     */
    public static function compare(array $baselines, array $candidates, string $identity, string $mode): BenchmarkComparison
    {
        if (count($baselines) !== BenchmarkComparison::ROUNDS || count($candidates) !== BenchmarkComparison::ROUNDS) {
            throw new RuntimeException('Measurement rounds differ');
        }
        $times = ['baseline' => [], 'candidate' => []];
        $settings = null;
        foreach (['baseline' => $baselines, 'candidate' => $candidates] as $version => $reports) {
            foreach ($reports as $report) {
                $sample = $report->sample($identity, $mode);
                $settings ??= [$sample['revs'], $sample['warmup']];
                if ($settings !== [$sample['revs'], $sample['warmup']]) {
                    throw new RuntimeException('Candidate comparison does not match its baseline measurement');
                }
                $times[$version] = [...$times[$version], ...$sample['times']];
            }
        }
        return new BenchmarkComparison(
            $candidates[0]->workloads[0],
            Statistics::median($times['baseline']),
            Statistics::median($times['candidate']),
        );
    }
}
