# Keep fast paths that the first decode in a request pays for

Compiled decoders live for one request, so every PHP-FPM request compiles the decoders it uses again. The first
decode in a request is therefore the cost that production pays most, and the Performance workflow blocks a change that
makes it more than 3% slower (see [Performance](../performance.md)). So the code keeps fast paths that a general
rule would make unnecessary. Each one was measured; removing one needs a new measurement that stays within the limit.

## The fast paths

| Fast path | Without it |
|---|---|
| Native builtin scalar fields become a `ScalarNode` directly in `TypeCompiler::native()`, without `NodeCompiler`. | GitHub pull request webhook 5.9% and record batch 1 4.8% slower on the first PHP-FPM decode ([#69](https://github.com/eventjet/eventjet-json/pull/69)). |
| Native class and enum fields compile through `NamedTypeCompiler::field()`, without a type tree and `UnionBuilder`. | First PHP-FPM decode 6–10% slower for the Stripe, GitHub and Kubernetes documents ([#84](https://github.com/eventjet/eventjet-json/pull/84)). |
| `PhpDocFieldType::narrowing()` compares a single PHPDoc type with a single native type, so that `PhpDocNarrowing` loads only for unions. | Loading a class costs about 4,200 instructions per request. Record batch 0, whose only field is `list<Record>` on `array`, failed the check at +3.84% (dbe5047). |
| `PhpDocSimpleTags` reads simple doc comments without the full parser. | First PHP-FPM decode 10–34% slower, see [ADR 0001](0001-built-in-phpdoc-parser.md). |
| `ClassDecoder::decode()` checks values that already have a field's scalar type itself, without calling the node. | Record batches 35% slower in warm decodes ([#80](https://github.com/eventjet/eventjet-json/pull/80)). A shared helper cost 13–15%. |

## Consequences

Some rules exist twice: once in the general path, and once in a fast path that must agree with it. Unit tests under
`tests/Unit/Optimization` pin the fast paths, because removing one changes no result.
