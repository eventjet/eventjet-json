<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Performance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Performance\Fixtures\MappedFields;
use Eventjet\Json\Test\Performance\Fixtures\MappedProperties;
use Eventjet\Json\Test\Performance\Fixtures\PlainFields;
use Eventjet\Json\Test\Performance\Fixtures\PlainProperties;
use JsonException;
use PhpBench\Attributes as Bench;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @api Executed by PHPBench. */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\ParamProviders('scenarios')]
#[Bench\Groups(['field-mapping'])]
#[Bench\Iterations(15)]
#[Bench\OutputTimeUnit('microseconds')]
final class FieldMappingBench
{
    private object $original;
    private object|null $decoded = null;
    private string $encoded = '';
    private string $json = '';

    public function __construct()
    {
        $this->original = new PlainFields();
    }

    /** @return iterable<string, array{mapped: bool, properties: bool}> */
    public function scenarios(): iterable
    {
        yield 'plain constructor' => ['mapped' => false, 'properties' => false];
        yield 'mapped constructor' => ['mapped' => true, 'properties' => false];
        yield 'plain properties' => ['mapped' => false, 'properties' => true];
        yield 'mapped properties' => ['mapped' => true, 'properties' => true];
    }

    /** @param array{mapped: bool, properties: bool} $parameters */
    public function setUp(array $parameters): void
    {
        $this->original = match ([$parameters['mapped'], $parameters['properties']]) {
            [true, true] => new MappedProperties(),
            [true, false] => new MappedFields(),
            [false, true] => new PlainProperties(),
            default => new PlainFields(),
        };
        $this->json = $parameters['mapped']
            ? '{"uri":"example","total":42,"valid":true}'
            : '{"ref":"example","count":42,"ready":true}';
    }

    /** @throws DecodeError */
    #[Bench\Groups(['cold'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchColdDecode(): void
    {
        $this->decode();
    }

    /** @throws DecodeError */
    #[Bench\Groups(['warm'])]
    #[Bench\Revs(20_000)]
    #[Bench\Warmup(1)]
    public function benchWarmDecode(): void
    {
        $this->decode();
    }

    /** @throws JsonException */
    #[Bench\Groups(['cold'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchColdEncode(): void
    {
        $this->encoded = json_encode($this->original, JSON_THROW_ON_ERROR);
    }

    /** @throws JsonException */
    #[Bench\Groups(['warm'])]
    #[Bench\Revs(20_000)]
    #[Bench\Warmup(1)]
    public function benchWarmEncode(): void
    {
        $this->encoded = json_encode($this->original, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     * @throws ExpectationFailedException
     */
    public function verify(): void
    {
        $encoded = $this->encoded;
        if ($this->decoded !== null) {
            Assert::assertEquals($this->original, $this->decoded);
            $encoded = json_encode($this->decoded, JSON_THROW_ON_ERROR);
        }
        if ($encoded !== $this->json) {
            throw new RuntimeException('Mapping changed the JSON field names or values.');
        }
    }

    /** @throws DecodeError */
    private function decode(): void
    {
        $decoded = Json::decode($this->json, $this->original::class);
        if ($decoded instanceof DecodeError) {
            throw $decoded;
        }
        $this->decoded = $decoded;
    }
}
