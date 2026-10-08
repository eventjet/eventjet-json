# Field mapping prototype — throwaway

Question: can `#[Field]` share its mapping with native `json_encode()` through a
trait, while requiring `JsonSerializable` on annotated classes? What happens to
the decoder's existing ban on custom serializers?

This is a logic prototype, not a production feature. It uses Composer's loader
to replace seven decoder internals only in the prototype process. The ordinary
library and its tests still load the unchanged `src/` implementation. The
prototype starts from PR #30 commit `2250767040281f0c3044e92137ae8351c9db7d0a`.

## Run

With the dedicated development container running:

```bash
docker compose -p eventjet-json-field-mapping exec php composer prototype-field-mapping
```

Choose a numbered case to inspect its input, decoded properties, native encoding,
and second decode. Use `q` to quit. For noninteractive output, append `-- --all`
and use `exec -T`. Each output line is one scenario observation; a failed round
trip in the deliberately inconsistent serializer is an expected finding.

If the container is not running:

```bash
docker compose -p eventjet-json-field-mapping up -d --build
docker compose -p eventjet-json-field-mapping exec php composer install
```

## Proposed API

```php
final readonly class Reference implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(#[Field('$ref')] public string $ref) {}
}
```

The example imports `Eventjet\Json\Field` and `Eventjet\Json\MappedJsonFields`.
Both exist only in the prototype overlay. The decoder and trait share cached
property-to-JSON names. The trait returns a `stdClass`, preserving object shape
for numeric names and empty objects. PHP performs encoding, including nested
objects, through the normal `JsonSerializable` hook.

## Observed contract

- Any annotation requires `JsonSerializable`, including a same-name annotation
  and an omitted field.
- A `JsonSerializable` class without annotations remains rejected.
- An annotated serializer is accepted, whether supplied by the trait or written
  manually. The trait is optional under the rule being explored.
- Duplicate JSON names are rejected, including collisions with ordinary fields.
- Constructor fields, public properties, inherited fields, nested lists, nulls,
  defaults, empty names, numeric names, and swapped names work in the displayed
  cases. Input errors use JSON paths such as `links[0].$ref`.
- Once renamed, a property's PHP name is not an additional input alias.
- Default-valued properties may appear on re-encoding, and unknown input members
  are discarded, as in the existing decoder. Object round trips and exact JSON
  preservation are shown separately.

**Verdict:** native encoding and shared field mapping work together. Requiring
`JsonSerializable` is necessary for this approach but does not prove that an
arbitrary `jsonSerialize()` obeys the mapping. The inconsistent serializer passes
the declaration rule, emits `wrong` instead of `$ref`, and fails the next decode.

The remaining product decision is whether to accept user-written serializers
under a documented responsibility to honor the mapping, or allow only the
library-provided mapping implementation. A marker interface or a trait name alone
would still not prove that the active method is the library implementation: PHP
classes can supply their own method. An enforceable restriction needs to account
for the effective method, inheritance, and overrides. This prototype intentionally
does not claim to solve that policy decision.

## Measurements

Raw observations are in `results/`. The benchmark uses PHP 8.4 in the same Compose
service, OPcache enabled, JIT and PCOV disabled. Each process measures 15 batches
of 100,000 operations. JSON documents have equal-length keys and identical values.
The three-field constructor case isolates mapping overhead; it is not a workload
representative of an application or a full schema.

| Path | Median decode, ns/call | Median native encode, ns/call |
| --- | ---: | ---: |
| Unchanged decoder, run 1 | 992.73 | 61.52 |
| Unchanged decoder, run 2 | 994.62 | 62.45 |
| Prototype, ordinary class, run 1 | 990.01 | 62.03 |
| Prototype, ordinary class, run 2 | 1005.19 | 62.48 |
| Prototype, mapped class, initial | 1098.17 | 300.55 |
| Mapped class, alternative array-function implementation | 1155.25 | 302.50 |
| Mapped class, retained loop, final run | 1154.41 | 336.00 |

The second series reverses execution order to help expose drift. Attribute
reflection and collision checks run when metadata is built, not during repeated
decodes. The encoding trait also reuses cached names without constructing a new
reflection object each call. Constructor conversion uses an additional renaming
pass only for mapped classes; ordinary classes have one extra empty-map check.

Replacing that pass with `array_intersect_key()` plus `array_combine()` did not
demonstrate an improvement, so the simpler loop was retained. Its final run also
slowed down, indicating run-to-run drift; these samples do not establish that one
mapping implementation is consistently faster. `mapped-final.json` records the
retained implementation. No performance warning was added to the library
README. These local measurements do not establish a general application-level
penalty. The first-decode samples include class loading and are too few for a
reliable cold-start comparison. Production adoption still needs realistic and
request-lifecycle benchmarks and evaluation of an integrated metadata design.

Reproduce the plain prototype run:

```bash
docker compose -p eventjet-json-field-mapping exec -T php php \
  -d pcov.enabled=0 -d opcache.enable_cli=1 \
  -d opcache.file_update_protection=0 -d opcache.jit=0 \
  prototypes/field-mapping/bench.php
```

Add `--mapped` for the annotated class. For the original decoder, add
`-e FIELD_PROTOTYPE_BASELINE=1` to `docker compose exec`; omit `--mapped`.
The baseline uses this branch's unchanged `src/` tree and the same dependencies.

## Limits

No production test suite or static-analysis approval is claimed for the overlay.
The interactive scenarios are executable observations, not a new test framework.
Full JSON Schema support, root descriptors, declaration validation in omitted or
empty nested collections, property hooks, dynamic properties, and a hardened
serializer opt-in policy are outside this prototype. In particular, a nullable
or omitted nested type may not reach the new declaration check until an object
of that type is actually decoded. The overlay duplicates internals to keep this
experiment isolated; it should not be merged into the library as-is.
