<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Closure;
use Eventjet\Json\Internal\Schema\Format\Dates;
use Eventjet\Json\Internal\Schema\Format\Emails;
use Eventjet\Json\Internal\Schema\Format\Hosts;
use Eventjet\Json\Internal\Schema\Format\IdnHosts;
use Eventjet\Json\Internal\Schema\Format\IpAddresses;
use Eventjet\Json\Internal\Schema\Format\UriPattern;
use Eventjet\Json\Internal\Schema\Format\Uris;

use function preg_match;

/**
 * Checks for the values of `format`.
 *
 * @internal
 */
final class Formats
{
    /**
     * Returns the check for a format, or null if the format is unknown, which makes it an annotation only.
     *
     * @return (Closure(string): bool)|null
     */
    public static function checker(string $format, Draft $draft): Closure|null
    {
        return match ($format) {
            'date-time' => Dates::dateTime(...),
            'date' => Dates::date(...),
            'time' => Dates::time(...),
            'duration' => Dates::duration(...),
            'email' => static fn(string $value): bool => Emails::email($value, $draft),
            'idn-email' => Emails::idnEmail(...),
            'hostname' => static fn(string $value): bool => Hosts::hostname($value, $draft),
            'idn-hostname' => IdnHosts::hostname(...),
            'ipv4' => IpAddresses::ipv4(...),
            'ipv6' => IpAddresses::ipv6(...),
            'uri', 'uri-reference', 'iri', 'iri-reference' => self::uri($format),
            'uri-template' => Uris::uriTemplate(...),
            'json-pointer' => Uris::jsonPointer(...),
            'relative-json-pointer' => Uris::relativeJsonPointer(...),
            'regex' => static fn(string $value): bool => EcmaRegex::toPcre($value) !== null,
            'uuid' => self::uuid(...),
            default => null,
        };
    }

    /** Returns whether checking the format needs the intl extension, for internationalized domain names. */
    public static function needsIntl(string $format, Draft $draft): bool
    {
        if ($format === 'idn-hostname' || $format === 'idn-email') {
            return true;
        }
        return ($format === 'hostname' || $format === 'email') && $draft->atLeast(Draft::Draft7);
    }

    /**
     * @param 'uri'|'uri-reference'|'iri'|'iri-reference' $format
     * @return Closure(string): bool
     */
    private static function uri(string $format): Closure
    {
        $pattern = match ($format) {
            'uri' => new UriPattern(UriPattern::UNRESERVED, '')->absolute(),
            'uri-reference' => new UriPattern(UriPattern::UNRESERVED, '')->reference(),
            'iri' => new UriPattern(UriPattern::UNRESERVED . UriPattern::UCSCHAR, UriPattern::IPRIVATE)->absolute(),
            'iri-reference' => new UriPattern(
                UriPattern::UNRESERVED . UriPattern::UCSCHAR,
                UriPattern::IPRIVATE,
            )->reference(),
        };
        return static fn(string $value): bool => Uris::matches($value, $pattern);
    }

    private static function uuid(string $value): bool
    {
        return (
            preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $value) === 1
        );
    }
}
