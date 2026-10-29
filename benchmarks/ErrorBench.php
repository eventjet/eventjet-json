<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\Benchmark\Fixtures\ScalarLists;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use JsonException;
use PhpBench\Attributes as Bench;
use RuntimeException;

use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/** @api Executed by PHPBench. */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\Iterations(5)]
#[Bench\Groups(['errors'])]
final class ErrorBench
{
    private string $json = '';
    /** @var class-string */
    private string $class = ScalarLists::class;
    private string $expectedPath = '';
    private object|null $decoded = null;

    /**
     * @param array{scenario: string} $parameters
     * @throws JsonException
     */
    public function setUp(array $parameters): void
    {
        if ($parameters['scenario'] === 'unknown enum') {
            $this->json = '{"stringStatus":"unknown","nullableStatus":null,"intStatus":1}';
            $this->class = BackedEnumFields::class;
            $this->expectedPath = 'stringStatus';
            return;
        }
        $lists = DecodeWorkloads::scalarLists(1000);
        $floats = $lists->floats;
        $index = $parameters['scenario'] === 'first list item' ? 0 : 999;
        $floats[$index] = 'invalid';
        $this->json = json_encode([
            'strings' => $lists->strings,
            'integers' => $lists->integers,
            'floats' => $floats,
            'booleans' => $lists->booleans,
        ], JSON_THROW_ON_ERROR);
        $this->class = ScalarLists::class;
        $this->expectedPath = 'floats[' . $index . ']';
    }

    /** @return iterable<string, array{scenario: string}> */
    public function scenarios(): iterable
    {
        foreach (['first list item', 'last list item'] as $scenario) {
            yield $scenario => ['scenario' => $scenario];
        }
    }

    /** @return iterable<string, array{scenario: string}> */
    public function enumScenario(): iterable
    {
        yield 'unknown enum' => ['scenario' => 'unknown enum'];
    }

    #[Bench\ParamProviders('enumScenario')]
    #[Bench\Revs(10_000)]
    #[Bench\Warmup(1)]
    public function benchWarmEnumError(): void
    {
        $this->decode();
    }

    #[Bench\ParamProviders('scenarios')]
    #[Bench\Revs(100)]
    #[Bench\Warmup(1)]
    public function benchWarmError(): void
    {
        $this->decode();
    }

    /** @throws RuntimeException */
    public function verify(): void
    {
        if (
            !$this->decoded instanceof DecodeError || !str_contains($this->decoded->getMessage(), $this->expectedPath)
        ) {
            throw new RuntimeException('Expected a decoding error at ' . $this->expectedPath);
        }
    }

    private function decode(): void
    {
        $this->decoded = Json::decode($this->json, $this->class);
    }
}
