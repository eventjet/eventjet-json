# Direct JSON parsing investigation

Keep native `json_decode()` followed by typed hydration as the default for now.
The [direct parsing prototype](../../experiments/direct-json/README.md) now measures
the tradeoff: removing the intermediate tree reduces allocation for object graphs
and ignored subtrees. The second pass compiles whole type graphs, improving runtime
on record collections and several real documents, beyond the narrower gains in
the first pass. Some documents and large retained strings still favor native
decoding, and schema compilation adds cold cost. See the
[second-pass results and optimization ledger](../../experiments/direct-json/RESULTS-SECOND-PASS.md)
for the measured limits. The prototype requires no FFI. Replacing the JSON parser also makes this package
responsible for maintaining JSON syntax and numeric behavior.
This decision is separate from the internal parser for PHPDoc types.

The current [decode entry point](../../src/Json.php) validates the complete document
before [hydration](../../src/Internal/ObjectHydrator.php) invokes any target constructor.
Malformed content in an ignored member or after the root therefore returns an
invalid-JSON error before construction or field validation. A container probe
confirmed zero child constructor calls for both cases, including a document with
an earlier field type mismatch. A valid control document invoked the child
constructor once. A replacement must preserve this ordering as well as returned
`DecodeError` values, existing error codes and messages, and underlying
construction exceptions.

[PHP's decoding API](https://www.php.net/manual/en/function.json-decode.php)
offers no target-class or construction callback. It distinguishes JSON objects
from arrays, validates UTF-8, limits nesting, and converts integers outside the
platform range to floats by default. A direct parser must retain those behaviors
and fully consume the input, including ignored members. It must also handle
escapes, Unicode surrogate pairs, number syntax, and trailing content correctly.
Token kinds must preserve `{}` versus `[]` before PHP containers are created;
the existing list, tuple, map-key, enum, and union validation rules still apply.
Duplicate member names remain outside the supported round-trip domain.

The [typed-json prototype at `dd55eaa`](https://github.com/MidnightDesign/typed-json/tree/dd55eaa788da70767e257ecf6591e68fc2eb4e42)
demonstrates a lexer, token lookahead, recursive type-directed parsing, and a
separate object factory. It collects an object's members before calling its
constructor. This is a useful design reference, not a runtime dependency or a
compatible replacement. Its parser lacks a final end-of-input check and starts
with a byte array from `str_split()`. Its lexer has incomplete number and escape
handling; its factory does not preserve omitted optional constructor defaults.
The inspected revision supplies no tests or benchmarks demonstrating correctness
or a performance advantage.

There are three approaches worth distinguishing:

| Approach | Construction and compatibility | Runtime and memory implications |
| --- | --- | --- |
| Native decoding, then hydration (current) | Syntax errors precede target construction. Existing validation and construction rules remain separate from JSON parsing. | Builds a generic tree before the target graph; native parsing handles JSON syntax. |
| One-pass typed parsing | Constructing a child as soon as its object closes can run user code before a later syntax error. Deferring construction requires retaining values, source spans, or construction plans until the complete document is valid. | Could remove generic objects, but retained plans and constructor arguments still consume memory. A PHP lexer is not proven faster than native parsing. |
| Native validation, then typed parsing | A complete validation pass can preserve the syntax-before-construction boundary. The typed parser still needs compatible value decoding and hydration rules. | Avoids a generic tree, but scans the document again and still retains the input string and final object graph. |

[`json_validate()`](https://www.php.net/manual/en/function.json-validate.php)
checks syntax without building a decoded tree. It is a possible first pass for
the third approach, not an extra step to add before the current `json_decode()`:
PHP documents that combination as unnecessary double parsing. Direct parsing
would still need to collect named constructor arguments regardless of member
order, distinguish missing members from explicit `null`, preserve defaults,
and avoid assigning promoted readonly properties twice. It must retain the
current validation order rather than report whichever type error appears first
in the JSON text. It would not make the existing string-in, object-out API a
bounded-memory streaming API.

The prototype also found that native validation is not equivalent to native
decoding in every detail: `json_validate()` accepts property names beginning with
`\u0000`, while `json_decode()` rejects them. The replacement must check names
inside ignored members too, before construction. On malformed input, the native
error path preserves invalid-property-name versus later syntax-error precedence.

Reconsider replacement only with a dependency-free prototype that passes the
existing acceptance and error-contract cases and generated differential checks
against native decoding. Include malformed ignored subtrees, trailing content,
constructor side effects, nesting boundaries, Unicode, numeric limits, and
object/array shape distinctions. Compare end-to-end time and peak memory on the
same PHP build with small documents, large lists and maps, deeply nested
objects, and large ignored fields, including validation and metadata costs.
The prototype performs these differential checks and records first-call and warmed
measurements. Its results establish a workload-specific benefit, not a universal
replacement or a claim that PHP parsing outperforms the native JSON engine.
Evaluate deployment runtimes and the cost of maintaining the additional parser
before promoting any experimental path into the public decoder.
