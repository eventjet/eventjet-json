# Leave declaration checks to static analysis

We assume that users run static analysis such as PHPStan or Psalm, which already reports PHPDoc types that contradict
a field's native type. So the decoder does not repeat that check at runtime, and it reads PHPDoc only to learn what it
needs for decoding (decided on 2026-10-13). [Types](../types.md) documents the result for users:

- A PHPDoc type that contradicts the native type gives an unspecified result.
- A PHPDoc type that names something the decoder cannot resolve is rejected on every field, even when the JSON member
  is missing (decided on 2026-10-29). With `@param 'a'|Missing $s` on a `string` field, `Json::decode()` and
  `Json::validateType()` return an `UnsupportedDeclaration` error.

Static analysis does not cover the second rule: PHPStan and Psalm accept type aliases such as `@phpstan-type` and
trust them, but the decoder cannot read them. Unknown class names and typos are rejected by the same rule, because the
decoder cannot tell them apart from aliases.

## Considered options

- **Reject contradicting PHPDoc types.** This would catch `@param 'a'|'b'` on a `?string` field at runtime. It was
  rejected because it duplicates what static analysis already reports.
- **Ignore an unresolvable PHPDoc type, so the native type applies.** Scalar and enum fields did this until
  2026-10-29. With `@phpstan-type Status 'a'|'b'` and `@param Status $s`, any string decoded, while static analysis
  assumed `'a'|'b'`.
- **Drop the unresolvable member and decode the rest.** This was the rule stated on 2026-10-13: an unknown member is
  fine as long as another member can be decoded. With `@param Status|null $s`, only `null` would decode, so valid
  input would fail at runtime while `Json::validateType()` reported no problem.

Rejecting was chosen because the other options can still be adopted later without a breaking change: they only turn
errors into successful decodes. Changing from either of them to rejecting would break classes that decode today.

## Consequences

- Collection fields, scalar fields, enum fields, and class fields follow the same rule, and so do constants such as
  `self::MISSING`.
- Known refinements such as `positive-int` and `non-empty-string` still read as their base type. They are not
  affected.
- Supporting more types later, such as type aliases or more keywords, is not a breaking change.
