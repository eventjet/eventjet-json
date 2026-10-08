<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields;
use InvalidArgumentException;
use JsonException;
use PhpBench\Attributes as Bench;
use RuntimeException;

use function is_array;
use function is_string;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @api Executed by PHPBench. */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\Iterations(5)]
#[Bench\OutputTimeUnit('milliseconds')]
final class DecodeBench
{
    private string $json = '';

    /** @var class-string|JsonType<list<CombinedCollectionFields>> */
    private string|JsonType $target = CombinedCollectionFields::class;

    /** @var list<mixed>|object|null */
    private array|object|null $decoded = null;

    /**
     * @param array{scenario: string} $parameters
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function setUp(array $parameters): void
    {
        $original = DecodeWorkloads::create($parameters['scenario']);
        $this->json = json_encode($original, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $this->target = is_array($original) ? JsonType::array(CombinedCollectionFields::class) : $original::class;
    }

    /** @throws DecodeError */
    #[Bench\ParamProviders(DecodeWorkloads::class . '::diagnosticScenarios')]
    #[Bench\Groups(['cold', 'diagnostic'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchCold(): void
    {
        $this->decode();
    }

    /** @throws DecodeError */
    #[Bench\ParamProviders(DecodeWorkloads::class . '::stressScenarios')]
    #[Bench\Groups(['cold', 'stress'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchColdStress(): void
    {
        $this->decode();
    }

    /** @throws DecodeError */
    #[Bench\ParamProviders(DecodeWorkloads::class . '::smallScenarios')]
    #[Bench\Groups(['warm', 'diagnostic'])]
    #[Bench\Revs(2000)]
    #[Bench\Warmup(1)]
    public function benchWarm(): void
    {
        $this->decode();
    }

    /** @throws DecodeError */
    #[Bench\ParamProviders(DecodeWorkloads::class . '::listScenario')]
    #[Bench\Groups(['warm', 'diagnostic'])]
    #[Bench\Revs(100)]
    #[Bench\Warmup(1)]
    public function benchWarmLists(): void
    {
        $this->decode();
    }

    /** @throws DecodeError */
    #[Bench\ParamProviders(DecodeWorkloads::class . '::stressScenarios')]
    #[Bench\Groups(['warm', 'stress'])]
    #[Bench\Revs(20)]
    #[Bench\Warmup(1)]
    public function benchWarmStress(): void
    {
        $this->decode();
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    public function verify(): void
    {
        $json = json_encode($this->decoded, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if ($json !== $this->json) {
            throw new RuntimeException('Decoding changed the expected JSON representation.');
        }
    }

    /** @throws DecodeError */
    private function decode(): void
    {
        $target = $this->target;
        $decoded = is_string($target) ? Json::decode($this->json, $target) : Json::decode($this->json, $target);
        if ($decoded instanceof DecodeError) {
            throw $decoded;
        }
        $this->decoded = $decoded;
    }
}
