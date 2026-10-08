<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use InvalidArgumentException;
use JsonException;
use PhpBench\Attributes as Bench;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

/** @api Executed by PHPBench. */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\Iterations(5)]
#[Bench\OutputTimeUnit('milliseconds')]
final class DocumentBench
{
    private DocumentWorkload|null $workload = null;
    private object|null $decoded = null;

    /**
     * @param array{scenario: string} $parameters
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function setUp(array $parameters): void
    {
        $this->workload = DocumentWorkloads::named($parameters['scenario']);
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    #[Bench\ParamProviders(DocumentWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'cold'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchCold(): void
    {
        $this->decode();
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    #[Bench\ParamProviders(DocumentWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'warm'])]
    #[Bench\Revs(200)]
    #[Bench\Warmup(1)]
    public function benchWarm(): void
    {
        $this->decode();
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    #[Bench\ParamProviders(DocumentWorkloads::class . '::smallBatches')]
    #[Bench\Groups(['batches', 'warm'])]
    #[Bench\Revs(10_000)]
    #[Bench\Warmup(1)]
    public function benchWarmSmallBatch(): void
    {
        $this->decode();
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    #[Bench\ParamProviders(DocumentWorkloads::class . '::allBatches')]
    #[Bench\Groups(['batches', 'cold'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchColdBatch(): void
    {
        $this->decode();
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    #[Bench\ParamProviders(DocumentWorkloads::class . '::largeBatches')]
    #[Bench\Groups(['batches', 'warm'])]
    #[Bench\Revs(200)]
    #[Bench\Warmup(1)]
    public function benchWarmLargeBatch(): void
    {
        $this->decode();
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     * @throws ExpectationFailedException
     */
    public function verify(): void
    {
        if ($this->decoded === null || $this->workload === null) {
            throw new RuntimeException('No document was decoded.');
        }
        $this->workload->verify($this->decoded);
    }

    /**
     * @throws DecodeError
     * @throws RuntimeException
     */
    private function decode(): void
    {
        if ($this->workload === null) {
            throw new RuntimeException('No document workload was selected.');
        }
        $decoded = Json::decode($this->workload->json, $this->workload->class);
        if ($decoded instanceof DecodeError) {
            throw $decoded;
        }
        $this->decoded = $decoded;
    }
}
