<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use JsonException;
use RuntimeException;

use function sprintf;
use function str_starts_with;

/**
 * Turns the cases of a directory of the official JSON Schema Test Suite into named tests.
 *
 * @internal
 */
final readonly class OfficialSuiteTests
{
    /**
     * @param string $prefix The start of the name of each test.
     * @param OfficialSuiteRounding $rounding Decides whether each test is valid as PHP represents its data.
     */
    public function __construct(
        private string $prefix,
        private OfficialSuiteRounding $rounding,
    ) {}

    /**
     * Returns the tests of the case by name. A format case without a tag holds whether formats assert or not, so it
     * runs both ways.
     *
     * @param object{description: string, tags?: list<string>, schema: mixed, externalSchemas?: mixed, tests: list<object{description: string, data: mixed, valid: bool}>} $case
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @param string $file The path of the case's file below the directory.
     * @return iterable<string, array{OfficialSuiteSchema, mixed, bool}> The schema, the value and whether it is valid.
     * @throws JsonException
     * @throws RuntimeException
     */
    public function ofCase(object $case, string $class, string $file, string $draft): iterable
    {
        $schema = OfficialSuiteSchema::ofCase($case, $class);
        $variants = [$draft => $schema];
        $untaggedFormat = ($case->tags ?? []) === [] && str_starts_with($file, 'format-');
        if ($untaggedFormat) {
            $variants[$draft . ', formats asserted'] = $schema->withAssertedFormats();
        }
        foreach ($variants as $variant => $variantSchema) {
            foreach ($case->tests as $test) {
                $name = sprintf(
                    '%s%s [%s]: %s: %s',
                    $this->prefix,
                    $file,
                    $variant,
                    $case->description,
                    $test->description,
                );
                yield $name => [$variantSchema, $test->data, $this->rounding->validity($file, $case, $test)];
            }
        }
    }
}
