<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;
use SimpleXMLElement;

require_once __DIR__ . '/BenchmarkWorkload.php';
require_once __DIR__ . '/BenchmarkComparison.php';

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
                    foreach ($subject->group as $group) {
                        $name = (string) $group['name'];
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

    private function measuredVariant(string $identity, string $mode): SimpleXMLElement
    {
        if ($this->identities() !== [$identity]) {
            throw new RuntimeException('Measured workload differs from the assigned workload');
        }
        $this->requireMode($mode);
        $variant = $this->variants[0];
        if (count($variant->iteration) !== 20) {
            throw new RuntimeException('Measurement sampling differs');
        }
        return $variant;
    }

    public function compareAgainst(self $baseline, string $identity, string $mode): BenchmarkComparison
    {
        $base = $baseline->measuredVariant($identity, $mode);
        $candidate = $this->measuredVariant($identity, $mode);
        if (
            count($candidate->{'baseline-stats'}) !== 1
            || (string) $base->stats['mode'] !== (string) $candidate->{'baseline-stats'}['mode']
            || (string) $base['revs'] !== (string) $candidate['revs']
            || (string) $base['warmup'] !== (string) $candidate['warmup']
        ) {
            throw new RuntimeException('Candidate comparison does not match its baseline measurement');
        }
        return new BenchmarkComparison(
            $this->workloads[0],
            (float) $base->stats['mode'],
            (float) $candidate->stats['mode'],
        );
    }
}
