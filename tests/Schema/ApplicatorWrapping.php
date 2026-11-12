<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationFailure;

use function str_replace;

/**
 * A schema and value placed inside an applicator. If the value fails the schema, the wrapped value fails once for each
 * copy, with the copy's prefixes on the locations, plus the failures of the applicator itself.
 *
 * @internal
 */
final readonly class ApplicatorWrapping
{
    /**
     * @param list<array{string, string}> $copies The instance and keyword location prefix of each copy.
     * @param list<ValidationFailure> $own The failures of the applicator itself if the value fails.
     * @param list<ValidationFailure> $whenValid The failures of the applicator if the value matches.
     */
    public function __construct(
        public object $schema,
        public mixed $value,
        public array $copies,
        public array $own = [],
        public array $whenValid = [],
    ) {}

    /**
     * Returns the failures that the wrapped value has.
     *
     * @param list<ValidationFailure> $failures The failures of the value against the schema.
     * @return list<ValidationFailure>
     */
    public function failures(array $failures): array
    {
        if ($failures === []) {
            return $this->whenValid;
        }
        $wrapped = $this->own;
        foreach ($this->copies as [$instancePrefix, $keywordPrefix]) {
            foreach ($failures as $failure) {
                $wrapped[] = new ValidationFailure(
                    $instancePrefix . $failure->instanceLocation,
                    $keywordPrefix . $failure->keywordLocation,
                    str_replace('#', '#' . $keywordPrefix, $failure->absoluteKeywordLocation),
                    $failure->message,
                );
            }
        }
        return $wrapped;
    }
}
