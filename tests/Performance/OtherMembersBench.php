<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Performance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Performance\Fixtures\CollectedMembers;
use Eventjet\Json\Test\Performance\Fixtures\MappedFields;
use InvalidArgumentException;
use JsonException;
use PhpBench\Attributes as Bench;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Measures what collecting the other members of a JSON object costs, compared with the same class without them.
 *
 * @api Executed by PHPBench.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('verify')]
#[Bench\ParamProviders('scenarios')]
#[Bench\Groups(['other-members'])]
#[Bench\Iterations(15)]
#[Bench\OutputTimeUnit('microseconds')]
final class OtherMembersBench
{
    private object $original;
    private string $encoded = '';
    private string $json = '';

    public function __construct()
    {
        $this->original = new MappedFields();
    }

    /** @return iterable<string, array{others: int|null}> */
    public function scenarios(): iterable
    {
        yield 'without #[OtherMembers]' => ['others' => null];
        yield 'no other members' => ['others' => 0];
        yield 'three other members' => ['others' => 3];
    }

    /**
     * @param array{others: int|null} $parameters
     * @throws InvalidArgumentException
     */
    public function setUp(array $parameters): void
    {
        $others = $parameters['others'];
        $members = ['x-a' => new JsonValue(1), 'x-b' => new JsonValue('b'), 'c' => new JsonValue([true])];
        $this->original = $others === null
            ? new MappedFields()
            : new CollectedMembers(others: $others === 0 ? [] : $members);
        $this->json = $others === 3
            ? '{"uri":"example","total":42,"valid":true,"x-a":1,"x-b":"b","c":[true]}'
            : '{"uri":"example","total":42,"valid":true}';
    }

    /** @throws JsonException */
    #[Bench\Revs(20_000)]
    #[Bench\Warmup(1)]
    public function benchWarmEncode(): void
    {
        $this->encoded = json_encode($this->original, JSON_THROW_ON_ERROR);
    }

    /** @throws DecodeError */
    #[Bench\Revs(20_000)]
    #[Bench\Warmup(1)]
    public function benchWarmDecode(): void
    {
        $decoded = Json::decode($this->json, $this->original::class);
        if ($decoded instanceof DecodeError) {
            throw $decoded;
        }
        $this->encoded = json_encode($decoded, JSON_THROW_ON_ERROR);
    }

    /** @throws RuntimeException */
    public function verify(): void
    {
        if ($this->encoded !== $this->json) {
            throw new RuntimeException('Collecting changed the JSON members or their values.');
        }
    }
}
