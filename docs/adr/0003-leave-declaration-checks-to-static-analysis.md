# Leave declaration checks to static analysis

We assume that users run static analysis such as PHPStan or Psalm, which already reports PHPDoc types that contradict
a field's native type. So the decoder does not repeat that check at runtime, and it reads PHPDoc only to learn what it
needs for decoding (decided on 2026-10-13). The rules this leads to are documented for users in
[Types](../types.md):

- A PHPDoc type that contradicts the native type gives an unspecified result.
- On a scalar or enum field, a PHPDoc type that names a type the decoder does not know is ignored, and the native type
  applies.
- In a union, a member the decoder does not know is fine as long as another member can be decoded.

## Considered options

- **Reject contradicting or unknown PHPDoc types.** This would catch `@param 'a'|'b'` on a `?string` field at runtime.
  It was rejected because it duplicates what static analysis already reports.

## Consequences

Collection fields still reject unknown or unsupported declarations, even when the JSON member is missing. Their native
type is `array` or `ArrayObject`, which says nothing about the items, so there is no native type to fall back to.
