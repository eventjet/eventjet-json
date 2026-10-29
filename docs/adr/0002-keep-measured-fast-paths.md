# Keep fast paths that the blocking performance checks pay for

Compiled decoders live for one request, so every PHP-FPM request compiles the decoders it uses again. The Performance
workflow blocks a change that makes the first decode in a fresh PHP-FPM request, or warm decodes, more than 3% slower
(see [Performance](../performance.md)). So the code keeps fast paths that a general rule would make unnecessary. Each
one was measured; removing one needs a new measurement that stays within the limit.

## The fast paths, as of 2026-10-29

When a change removes or renames a fast path, it updates its row.

| Fast path | Without it |
|---|---|
| Native `bool`, `int`, `float`, and `string` fields, nullable or not, become a `ScalarNode` directly in `TypeCompiler::native()`, without `NodeCompiler`. | GitHub pull request webhook 5.9% and record batch 1 4.8% slower on the first PHP-FPM decode ([#69](https://github.com/eventjet/eventjet-json/pull/69)). |
| Native class and enum fields compile through `NamedTypeCompiler::field()`, without a type tree and `UnionBuilder`. | First PHP-FPM decode 6–10% slower for the Stripe, GitHub and Kubernetes documents ([#84](https://github.com/eventjet/eventjet-json/pull/84)). |
| `PhpDocFieldType::narrowing()` compares a single PHPDoc type with a single native type, so that `PhpDocNarrowing` loads only for unions. | Loading a class costs about 4,200 instructions per request. Record batch 0, whose only field is `list<Record>` on `array`, failed the check at +3.84% (dbe5047). |
| `ClassDecoders::decodeRoot()` calls `enum_exists()` only when no decoder of the class is cached, and asks `EnumRoots` only for an enum. The rules for where a class can be decoded live in `TargetClass`, which every decode loads anyway. So a warm decode of a class root skips `enum_exists()`, and the first decode of a class root loads no extra class for these rules or for enum roots. | Each class adds a class load per request, about 4,200 instructions, see the row above. Not measured on its own. |
| `PhpDocSimpleTags` reads simple doc comments without the full parser. | First PHP-FPM decode 10–34% slower, see [ADR 0001](0001-built-in-phpdoc-parser.md). |
| `ClassDecoder::decode()` checks values that already have a field's scalar type itself, without calling the node. | Record batches 35% slower in warm decodes ([#80](https://github.com/eventjet/eventjet-json/pull/80)). A shared helper cost 13–15%. |

## Consequences

Some rules exist twice: once in the general path, and once in a fast path that must agree with it. Unit tests under
`tests/Unit/Optimization` pin the fast paths, because removing one changes no result.
