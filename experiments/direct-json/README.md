# Direct JSON decoding prototype

This is an experiment against PR #30, not a replacement for `Json::decode()`.
It asks whether removing the intermediate `stdClass` tree improves end-to-end
runtime or peak allocation. Production code and its public API are unchanged.
The second pass adds whole-schema validation and generated cursor-based construction.
It uses PHP and the bundled JSON/PCRE facilities; no FFI or native add-on is required.

Install the project's Composer dependencies, then run:

```sh
php experiments/direct-json/verify.php
php experiments/direct-json/bench.php 5 native,window-8192,extreme
php experiments/direct-json/paired.php 15 native,window-8192,extreme
```

The first command checks the existing acceptance providers, real documents,
seeded generated inputs, syntax mutations, numeric and Unicode boundaries,
constructor side effects, invalid property names, and nesting boundaries.
It exits nonzero on a difference. It does not modify PHPUnit's acceptance API.

The second command emits JSON to stdout and a progress summary to stderr.
Every sample uses a fresh subprocess; modes are shuffled deterministically
within each sample. Each worker reports first-call time/allocation, warmed time,
warmed peak allocation, and retained output size. It checks output equality
against the production decoder outside the measurement region. Workload creation,
autoload setup, input storage and metadata already cached during warmup are
excluded from warmed peak allocation. The complete returned graph is included.
These are PHP allocator measurements, not process RSS. Runtime is elapsed time,
used as a CPU-cost proxy, not a `getrusage()` CPU counter.

An optional third argument selects comma-separated workloads:

```sh
php experiments/direct-json/bench.php 5 native,window-8192 "record batch 1000,root records 10000"
php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M experiments/direct-json/bench.php 5 native,window-8192
```

Workers inherit the loaded INI file and the explicit OPcache/JIT and PCRE settings.
On PHP versions where OPcache is a shared extension, enable it in that INI file.
No PHP, Composer or tool configuration is added to the repository by this experiment.

## Modes

| Mode | Experiment |
| --- | --- |
| `native` | Unmodified `Json::decode()`. |
| `pure` | Custom scalar, string/Unicode and collection parsing after native syntax validation. |
| `hybrid` | Decode escaped string tokens and scalar collection subtrees natively; construct class graphs directly. |
| `native-strings` | Also decode unescaped string tokens natively. |
| `compiled` | Cache schema-specific scalar-object PCRE patterns and constructor closures; native scalar-token decoding. |
| `regex` | Add PCRE subtree scanning and direct scalar conversion. |
| `ordered` | Prefer declaration-order matches, with an unordered/general fallback. |
| `chunks-N` | Parse up to N scalar objects per group; named captures. |
| `packed-N` | Use numbered captures to reduce intermediate allocation. |
| `window-N` | Parse complete scalar objects within an N-byte source window, avoiding the extra chunk-boundary scan. |
| `validate-window-N` | Use a bounded custom JSON grammar before the window parser; fall back to native validation when necessary. |
| `lazy-window-N` | Store linked error-path components; avoid building successful scalar/class paths and materialize them at error or converter boundaries. |
| `validate-lazy-window-N` | Combine deferred paths and the custom validation fast path. |
| `indexed-lazy-window-N` | Cache container end offsets once for documents up to 64 KiB and 1,024 delimiters, avoiding repeated scans in deep graphs. |
| `columns-N` | Column-oriented captures and a generated loop construct a window of scalar records together. |
| `fused` | One strict match validates and captures a small scalar root object. |
| `projection`, `projection-ascii` | Fused scalar-root parsing with allocation-free validation of ignored subtrees up to three container levels. The ASCII variant falls back on raw non-ASCII bytes. |
| `fused-batch` | Capture an entire strict scalar-record batch before construction; an allocation-heavy alternative to cursor parsing. |
| `graph` | Compile the complete type graph to a strict grammar and forward cursor constructors. |
| `extreme` | Combined path: small fused object, ASCII/Unicode projection, compact inlined graph, flexible inlined graph, then column-window fallback. |

Graph mode suffixes expose individual optimization experiments: `compact` removes
whitespace handling; `flex` allows omitted defaults and flexible public-property
order; `inline` emits scalar reads directly; `bulk` decodes validated scalar
collection leaves natively; `registers` replaces property/value pairs with locals;
`direct` uses presence masks where property assignment order is unobservable;
`loop` inlines scalar-only record constructors into list loops; `ascii` removes
the UTF-8 prepass only for grammars that exclude raw non-ASCII bytes; `trie`
factors member-name alternatives; `guard` tests whitespace before skipping it;
`header` matches a key, colon and whitespace together. The verifier lists exact
combinations. Slower variants remain available for comparison.

`paired.php` corroborates warmed timings using randomized approximately 25 ms
blocks in one process and reports the median per-round ratio to `native`.
It requires `native` among its modes. It does not measure cold cost or memory.
`profile.php` splits graph validation and construction for diagnosis; its
construction-only measurement assumes already validated input and is not a
compatible standalone decoder.

`native-compiled` is a **diagnostic control**, available for record workloads:
native JSON decoding followed by a hand-written, unvalidated record hydrator.
It measures how much of the improvement could come from construction alone.
It is not an alternative decoder with the same error/declaration contract.

`trusted-window-N` is another **diagnostic control**. It assumes input was already
validated and omits the syntax-before-construction guarantee. Only use it with
the generated valid benchmark inputs. Its results are excluded from compatible
parser comparisons.

A `-nogc` suffix disables cycle collection for a worker as another diagnostic.
It is not a decoder feature and does not change the normal parser's GC settings.
Extra workloads include `deep chain 128`, `deep chain 384`, `large string field`,
and `enum-heavy object collections`.

## Construction and compatibility

The general parser retains scalar values and source offsets for each known member
of the current object. It validates constructor scalar types before converting
children, converts arguments in declaration order, prepares public properties,
and then invokes the constructor. Unknown values are scanned without retaining
their contents. Omitted arguments are omitted from the call, preserving defaults.
JSON objects and arrays retain distinct token kinds.

The fast path compiles eligible scalar, enum, scalar-union and public-property schemas.
Named enum cases are cached with their backing types, so numeric strings cannot
bypass strict enum checks. The original window fast paths require declaration order
to preserve observable property-hook and readonly-assignment behavior.
PHP source is generated
from reflection metadata, never JSON input. Class aliases resolve to the declared
identifier. Partial, reordered, escaped-key and mismatched inputs fall back before
construction. PCRE resource failures use the general scanner. A possessive scanner
may temporarily raise its linear work budget and restores it before user code runs.
Scalar-object captures are capped at a 32 KiB source window; a larger retained
string uses the general parser instead of making multiple large PCRE copies.
The optional end-offset index is bounded to 64 KiB documents with at most 1,024
opening delimiters. It avoids scanning the same nested subtree repeatedly while
retaining offsets rather than decoded values. It is a targeted deep-graph experiment,
not the default: indexing flat documents can increase both time and allocation.

All ordinary modes validate the entire document before target construction.
`json_validate()` alone is insufficient: it accepts leading-NUL property names
that `json_decode()` rejects. A bounded lexical key check handles that case even
inside ignored subtrees. Malformed documents use the native error path to retain
invalid-property-name versus syntax-error precedence.

The custom validator verifies JSON whitespace, number grammar, UTF-8, control
characters, escapes and surrogate pairs. It only replaces native validation when
fewer than 512 opening delimiters occur anywhere in the source, including strings.
That conservative count proves the nesting limit cannot be exceeded. Large or
deep documents, grammar mismatches, and PCRE resource failures fall back to native
validation. No target constructor runs during either validation strategy.

The whole-graph compiler additionally validates field types before constructing
anything. Eligible acyclic schemas prove the nesting limit statically, avoiding
a separate `json_validate()` pass even for large flat collections. Recursive or
larger schemas retain a conservative native depth check. Generated functions
consume the validated source with a shared cursor; ordinary paths avoid rescanning
each nested object. Optional child arguments and interleaved public children are
deferred when needed to preserve construction order. Readonly and asymmetric
setters use reflection; hooks, magic setters and constructor-created references
prevent assignment reordering. Syntax/type mismatches use the fallback before
any constructor runs. All five document fixtures exercise the flexible graph path.

Scalar collections can still use `json_decode()` on a leaf. This avoids building
a generic object graph to copy into target objects, but it does not claim every
token is parsed in PHP. Generated code and compiled patterns are process-local
caches; cold schema compilation and persistent cache growth must be considered
separately from warmed throughput.

The existing converters remain authoritative for enums, scalar unions, collection
shape rules and errors. Some fallback/error paths materialize a single subtree.
This is not a bounded-memory streaming API: the input string and complete result
remain in memory, and class-level caches grow with encountered declarations.

See [the second-pass results](RESULTS-SECOND-PASS.md) for the current conclusion
and [RESULTS.md](RESULTS.md) for the historical first-pass measurements.
