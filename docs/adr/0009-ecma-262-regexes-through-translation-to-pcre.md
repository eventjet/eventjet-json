# Give regexes ECMA-262 semantics by translating them to PCRE

JSON Schema says that `pattern` and `patternProperties` are ECMA-262 regexes, but PHP only has PCRE. The two
dialects disagree in ways that change results: PCRE's `$` also matches before a final line break, PHP's `u` flag
makes `\d`, `\w`, and `\b` match Unicode, `\s` matches different whitespace, Unicode property names differ, and some
escapes are valid in only one dialect. So the compiler parses each regex as ECMA-262 with the `u` flag and writes an
equivalent PCRE pattern (`src/Internal/Schema/EcmaRegex.php`), once per compile (decided on 2026-11-04,
[#124](https://github.com/eventjet/eventjet-json/issues/124)).

Passing patterns to PCRE unchanged would accept schemas written for PCRE and give JavaScript validators' users
different answers without anyone noticing. The optional `ecmascript-regex` tests of the official suite fail that way.

## Consequences

- A regex that isn't valid ECMA-262 is a `SchemaError`, and the `regex` format uses the same parser.
- The translator writes every literal as `\x{…}` and every class escape as an explicit set, so the PCRE pattern
  doesn't depend on PCRE options.
- PCRE can't run some valid ECMA-262 regexes, such as lookbehinds of unbounded length. Those are `SchemaError`s too.
- PCRE keeps the captures of a group across the repetitions of a quantified atom, but ECMA-262 forgets them at the
  start of each repetition. So `^(?:(a)|b\1)+$` matches `ab` in ECMA-262 only. A back reference to a group inside a
  quantified atom that can repeat makes the regex a `SchemaError` (decided on 2026-11-09).
- PCRE also accepts a repetition that matches the empty string once the minimum count is reached, where ECMA-262
  rejects it. That changes which text matches, and captures that only such a back reference could see, but never
  whether a regex without one matches. The translation keeps those regexes.
- `EcmaRegexTest` checks the translation against V8's verdicts on generated regexes (`.github/ci/ecma-regexes.mjs`):
  whether a regex is valid, and whether it matches each subject, not which text it matches. For the same reason, the
  translation drops the `?` that makes a quantifier lazy.
