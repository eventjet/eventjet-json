# Validate numbers as PHP represents them

The validator compares numbers as the `int` and `float` values that `json_decode()` gives, not as the decimals in the
JSON text (decided on 2026-11-12). Numbers that differ only beyond the precision of a `float` (about 17 significant
digits) or beyond `PHP_INT_MAX` are the same value to it.

The official JSON Schema Test Suite has optional cases that need more precision, in `optional/bignum.json`:

| Case | JSON text | PHP value |
|---|---|---|
| `maximum` and `minimum` integer comparison | `18446744073709551615` and `18446744073709551616` | both `1.8446744073709552E+19` |
| float comparison with high precision | `972783798187987123879878123.18878137` and `…123.188781369` | both `9.727837981879871E+26` |
| `enum`, `const` and `uniqueItems` comparison with high precision | `0.123456789123456789`, `…788` and `…790` | all `0.12345678912345678` |

The test runs these cases, but it expects the result of a sibling test whose data PHP rounds to the same value. The
list is in `tests/Schema/OfficialSuiteRounding.php`. The test fails if a listed case is missing from the suite or
doesn't share its data with a sibling that expects the opposite. So a change in how the validator treats these numbers
shows up.

## Considered options

- **Exact decimals on both sides.** The instance would need an entry point that takes JSON text, or a decoded
  representation that keeps such numbers. The schema would need decimal values for `maximum`, `minimum`, `enum` and
  `const`. But the schema classes promise that `json_encode()` writes the document back, and `json_encode()` can't
  write a number more precisely than a `float`. The other way to get exact schema numbers is for the validator to keep
  them from the schema text next to the schema objects. Then the validator checks something other than what the
  objects say.
- **Exact decimals in instances only.** This would fix only the `uniqueItems` cases. The other cases compare against
  a schema value that is already rounded, so they would still disagree with the suite.
- **Skipping the cases, or the whole file.** This hides a change in how the validator treats these numbers.
