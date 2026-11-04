# Build error paths while errors leave the nodes

Nodes used to receive the path of their value, such as `items[3].name`, and every list, map, tuple and object built the
path of each item and field before decoding it, although only errors use it. Now nodes receive no path. A `DecodeError`
for an invalid value starts with an empty path, and each enclosing object, list, map and tuple catches it, adds its
member name or key to the front, and throws it again (decided on 2026-11-03). A `try` block costs nothing until
something throws, so decoding valid values builds no paths. The messages stay the same.

`ClassDecoder::decode()` returns the errors of its fields instead of throwing them once more, so each object still throws
once, as [ADR 0004](0004-return-decode-errors-throw-them-inside.md) requires. It names its class in each error it returns
with `DecodeError::whileCreating()`, which every object and every root decode calls. For an error in a field, the same
call adds the member name to the path. It renders the path into the message, so that an error renders it once per
object, not once per member or key.

`InvalidValue` and `InvalidDiscriminator` create the errors of invalid values with `new DecodeError()` and pass the
arguments by position. An exception records the call stack when it is created, so a named constructor such as
`DecodeError::invalidValue()` would add a frame to every trace. Named arguments add work to every call.

## Measurement

Comparison runner on 2026-11-03 with OPcache on, run locally in the Docker image (baseline 15ffbee, candidate the
working tree). Fresh PHP-FPM requests, 80 alternating pool rounds of 10 requests per revision:

| Workload | Change |
|---|---:|
| JSON:API compound document | −1.04% |
| AWS Lambda Amazon MSK event | −2.17% |
| Stripe invoice | −1.01% |
| GitHub pull request webhook | −0.93% |
| Kubernetes deployment | −1.65% |
| record batch 0, 1, 100, 1000 | +0.95%, −0.89%, −4.02%, −5.94% |

Warm CLI decodes:

| Workload | Change |
|---|---:|
| Example documents | −4.2% to −13.6% |
| Record batches | −1.9% to −5.4% |
| Stress cases | −16.3% to −18.4% |
| Long scalar lists, long string map | −0.2%, −2.3% |

The same run reported public scalar properties at +10.6% in warm decodes. Three local alternating runs measured
1.17 µs against 1.18 µs, so it was noise.

## Rejected values

At first, an unknown enum value in a tiny root object (`ErrorBench`, unknown enum) was about 6% slower: +6.17% with
OPcache on and +5.76% with OPcache off on CI ([run](https://github.com/eventjet/eventjet-json/actions/runs/41806406770)).
Before, the field of a root object needed no path work at all. Two steps had brought it down from 11%: returning field
errors from `ClassDecoder::decode()` instead of throwing them again, and rendering the message once in `whileCreating()`
instead of after every member or key. In a local loop of 10,000 decodes per process, with 40 to 80 alternating rounds,
these steps removed most of the rest:

| Step | Unknown enum value |
|---|---:|
| `new DecodeError()` instead of the named constructor `DecodeError::invalidValue()`, whose frame every trace recorded | about −2% to −3% |
| The flag for invalid values by position instead of by name | about −0.5% to −1% |
| `whileCreating()` adds the member name, instead of a separate `inMember()` call per object | about −1.5% |

Local PHPBench on 2026-11-04 with production `php.ini` settings, 24 alternating rounds of two iterations per revision,
against master (a59d951): the unknown enum value is 2.58% slower with OPcache on and 0.70% slower with OPcache off. The
list error cases stay within 1.2%. Against the candidate before these steps, successful decodes changed by −3.2% to
+2.1%. A comparison of master with itself varied by up to 3.5%.

## Consequences

A `DecodeError` of an invalid value shows its full path only after `whileCreating()`. Code that returns such an error
to callers must call it, as `ClassDecoder` and `RootDescriptor` do.
