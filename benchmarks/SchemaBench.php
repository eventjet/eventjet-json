<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\Schema\Validator;
use InvalidArgumentException;
use JsonException;
use PhpBench\Attributes as Bench;
use RuntimeException;

/** @api Executed by PHPBench. */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\Iterations(5)]
#[Bench\OutputTimeUnit('microseconds')]
final class SchemaBench
{
    private SchemaWorkload|null $workload = null;
    private Validator|null $validator = null;

    /**
     * @param array{scenario: string} $parameters
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function setUp(array $parameters): void
    {
        $this->workload = SchemaWorkloads::named($parameters['scenario']);
        $this->validator = $this->workload->compile();
    }

    /** @throws RuntimeException */
    #[Bench\ParamProviders(SchemaWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'cold'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchCompileAndValidateCold(): void
    {
        $this->validator = $this->workload()->compile();
        $this->validator->validateValue($this->workload()->value);
    }

    /** @throws RuntimeException */
    #[Bench\ParamProviders(SchemaWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'warm'])]
    #[Bench\Revs(50)]
    #[Bench\Warmup(1)]
    public function benchCompileWarm(): void
    {
        $this->validator = $this->workload()->compile();
    }

    /** @throws RuntimeException */
    #[Bench\ParamProviders(SchemaWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'warm'])]
    #[Bench\Revs(50)]
    #[Bench\Warmup(1)]
    public function benchValidateWarm(): void
    {
        $this->validator()->validateValue($this->workload()->value);
    }

    /** @throws RuntimeException */
    #[Bench\ParamProviders(SchemaWorkloads::class . '::scenarios')]
    #[Bench\Groups(['documents', 'warm'])]
    #[Bench\Revs(50)]
    #[Bench\Warmup(1)]
    public function benchIsValidWarm(): void
    {
        $this->validator()->isValid($this->workload()->value);
    }

    /** @throws RuntimeException */
    public function verify(): void
    {
        $this->workload()->verify($this->validator());
    }

    /** @throws RuntimeException */
    private function workload(): SchemaWorkload
    {
        return $this->workload ?? throw new RuntimeException('No workload was set up.');
    }

    /** @throws RuntimeException */
    private function validator(): Validator
    {
        return $this->validator ?? throw new RuntimeException('No validator was compiled.');
    }
}
