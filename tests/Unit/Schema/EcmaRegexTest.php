<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Schema;

use Eventjet\Json\Internal\Schema\EcmaRegex;
use Eventjet\Json\Internal\Schema\Regex\Atom;
use Eventjet\Json\Internal\Schema\Regex\AtomEscape;
use Eventjet\Json\Internal\Schema\Regex\CharacterClass;
use Eventjet\Json\Internal\Schema\Regex\CharacterEscape;
use Eventjet\Json\Internal\Schema\Regex\ClassAtom;
use Eventjet\Json\Internal\Schema\Regex\ClassEscape;
use Eventjet\Json\Internal\Schema\Regex\CodePoints;
use Eventjet\Json\Internal\Schema\Regex\Cursor;
use Eventjet\Json\Internal\Schema\Regex\Group;
use Eventjet\Json\Internal\Schema\Regex\GroupName;
use Eventjet\Json\Internal\Schema\Regex\Groups;
use Eventjet\Json\Internal\Schema\Regex\Pattern;
use Eventjet\Json\Internal\Schema\Regex\Quantifier;
use Eventjet\Json\Internal\Schema\Regex\UnicodeEscape;
use Eventjet\Json\Internal\Schema\Regex\UnicodeProperties;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function error_clear_last;
use function error_get_last;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function preg_match;
use function var_export;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

#[CoversClass(EcmaRegex::class)]
#[CoversClass(Atom::class)]
#[CoversClass(AtomEscape::class)]
#[CoversClass(CharacterClass::class)]
#[CoversClass(CharacterEscape::class)]
#[CoversClass(ClassAtom::class)]
#[CoversClass(ClassEscape::class)]
#[CoversClass(CodePoints::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(Group::class)]
#[CoversClass(GroupName::class)]
#[CoversClass(Groups::class)]
#[CoversClass(Pattern::class)]
#[CoversClass(Quantifier::class)]
#[CoversClass(UnicodeEscape::class)]
#[CoversClass(UnicodeProperties::class)]
final class EcmaRegexTest extends TestCase
{
    /**
     * The regexes of `ecma-regexes.json`, which `.github/ci/ecma-regexes.mjs` writes with the verdicts of V8.
     *
     * @return iterable<string, array{string, list<array{string, bool}>|'unsupported'|false}>
     *     The regex, and false if it isn't valid ECMA-262, "unsupported" if PCRE can't match it the same way, or else
     *     subjects with whether the regex matches them.
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function regexes(): iterable
    {
        $json = file_get_contents(__DIR__ . '/ecma-regexes.json');
        if ($json === false) {
            throw new RuntimeException('Could not read ecma-regexes.json.');
        }
        /** @var list<array{string, list<array{string, bool}>|'unsupported'|false}> $cases */
        $cases = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as [$regex, $verdict]) {
            $name = json_encode($regex, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            yield $name => [$regex, $verdict];
        }
    }

    /** @param list<array{string, bool}>|'unsupported'|false $verdict */
    #[DataProvider('regexes')]
    public function testPcreAgreesWithAnEcmaScriptEngine(string $regex, array|string|false $verdict): void
    {
        error_clear_last();
        $pcre = EcmaRegex::toPcre($regex);
        static::assertNull(error_get_last(), 'Translating leaves no PHP error.');
        if ($verdict === false) {
            static::assertNull($pcre, 'V8 rejects the regex.');
            return;
        }
        static::assertNotNull($pcre, 'V8 accepts the regex.');
        if ($verdict === 'unsupported') {
            static::assertFalse($pcre, 'PCRE can\'t match the regex the same way.');
            return;
        }
        static::assertNotFalse($pcre, 'PCRE can match the regex the same way.');
        foreach ($verdict as [$subject, $matches]) {
            static::assertSame(
                (int) $matches,
                preg_match($pcre, $subject),
                $pcre . ' on ' . var_export($subject, return: true),
            );
        }
    }
}
