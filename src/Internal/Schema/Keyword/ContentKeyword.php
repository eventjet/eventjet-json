<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function base64_decode;
use function is_string;
use function json_validate;
use function sprintf;

/**
 * `contentEncoding` and `contentMediaType` of Draft 7, which may check values. Later drafts made them annotations.
 *
 * @internal
 */
final readonly class ContentKeyword implements Keyword
{
    public function __construct(
        private bool $base64,
        private bool $json,
        private string $encodingLocation,
        private string $mediaTypeLocation,
    ) {}

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        if (!is_string($value)) {
            return true;
        }
        if ($this->base64) {
            $decoded = base64_decode($value, strict: true);
            if ($decoded === false) {
                return $run->fail(
                    $instanceLocation,
                    $keywordLocation . '/contentEncoding',
                    $this->encodingLocation,
                    'The value must be valid base64.',
                );
            }
            $value = $decoded;
        }
        if ($this->json && !json_validate($value)) {
            return $run->fail(
                $instanceLocation,
                $keywordLocation . '/contentMediaType',
                $this->mediaTypeLocation,
                sprintf('The value must be a valid %s document.', 'application/json'),
            );
        }
        return true;
    }
}
