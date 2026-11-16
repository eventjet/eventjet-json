# Report PCRE limits as their own error, and keep the built-in formats below them

PCRE gives up on a match when it reaches its backtrack limit or its JIT stack limit, and `preg_match()` then returns
`false`. The validator used to read that as "doesn't match" (decided on 2026-11-09). Values are untrusted
([ADR 0010](0010-treat-schemas-as-trusted-input.md)), and ordinary schemas reach the limits: `^(?:a|b)*$` exhausts the
JIT stack on a 100 KB string, and `^(a+)+$` exhausts the backtrack limit on 31 characters. Inside `not`, the wrong
"doesn't match" turned into a match, so a client could get a value accepted that the schema rejects.

A regex of the schema can't be made safe, so the run records that PCRE gave up, and `validateValue()` returns a
`ValidationError` of kind `RegexLimit` no matter which applicator the regex was in. The run records it instead of
throwing, because an exception would need a `@throws` on every keyword.

The regexes of the built-in formats are the validator's own, so they must give the right answer for any value. A single
match that repeats a group reaches the limits at about a million repetitions, even with possessive quantifiers: the
possessive JSON Pointer pattern still exhausted the backtrack limit on a 2 MB pointer. So the formats split values with
string functions and check each part with one PCRE match per unit (`Format\Units`), which handles 1.3 million units in
about 20 ms.

## Consequences

- `ValidationErrorKind` has a third case, `RegexLimit`, whose error lists no failures.
- The URI, IRI, URI template, JSON Pointer and email formats don't use one regex per value any more. A comparison with
  the old regexes over 1.35 million random short values found no difference.
