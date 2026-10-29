# Leave declaration checks to static analysis

We assume that users run static analysis such as PHPStan or Psalm, which already reports PHPDoc types that contradict
a field's native type or name unknown types. So the decoder does not repeat those checks at runtime, and it reads
PHPDoc only to learn what it needs for decoding (decided on 2026-10-13). [Types](../types.md) documents the result for
users:

- A PHPDoc type that contradicts the native type gives an unspecified result.
- On a scalar or enum field, if the PHPDoc type names a type the decoder does not know, the decoder ignores the whole
  PHPDoc type, and the native type applies. With `@param 'a'|Missing $s` on a `string` field, any string is accepted.

## Considered options

- **Reject contradicting or unknown PHPDoc types.** This would catch `@param 'a'|'b'` on a `?string` field at runtime.
  It was rejected because it duplicates what static analysis already reports.

## Consequences

- Collection fields reject unknown or unsupported declarations, even when the JSON member is missing, including unions
  inside them such as `list<int|Missing>`. Their native type is `array` or `ArrayObject`, which says nothing about the
  items, so there is no native type to fall back to.
- On a scalar field, an unknown constant such as `self::MISSING` is rejected instead of ignored. The rule above does
  not explain this exception.

## Open

On 2026-10-13 the rule was stated more broadly: an unknown member of a union is fine as long as another member can be
decoded. The code does not do that. One unknown member makes the decoder ignore the whole PHPDoc type on a scalar
field, and reject it on a collection field. Which behavior is wanted, and whether unknown constants should follow it,
is not decided yet.
