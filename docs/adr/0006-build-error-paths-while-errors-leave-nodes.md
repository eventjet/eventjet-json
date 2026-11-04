# Build error paths while errors leave the nodes

Nodes used to receive the path of their value, such as `items[3].name`, and every list, map, tuple and object built the
path of each item and field before decoding it, although only errors use it. Now nodes receive no path. A `DecodeError`
for an invalid value starts with an empty path, and each enclosing object, list, map and tuple catches it, adds its
member name or key to the front, and throws it again (decided on 2026-11-03). A `try` block costs nothing until
something throws, so decoding valid values builds no paths. The messages stay the same.

`ClassDecoder::decode()` returns the errors of its fields instead of throwing them once more, so each object still throws
once, as [ADR 0004](0004-return-decode-errors-throw-them-inside.md) requires. `DecodeError::whileCreating()`, which every
object and every root decode calls, renders the path into the message, so that an error renders it once per object, not
once per member or key.

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

## Accepted regression

An unknown enum value in a tiny root object (`ErrorBench`, unknown enum) is about 6.5% slower: 1.56 µs before, 1.67 µs
after, in three local alternating PHPBench runs of the final candidate. This fails the blocking 3% check, and the
regression was accepted for the gains above. Adding the member name and rendering the message cost about 110 ns. Before,
the field of a root object needed no path work at all. Two steps brought it down from 11%: returning field errors from
`ClassDecoder::decode()` instead of throwing them again, and rendering the message once in `whileCreating()` instead of
after every member or key. The list error cases stay within 1%.

## Consequences

A `DecodeError` of an invalid value shows its full path only after `whileCreating()`. Code that returns such an error
to callers must call it, as `ClassDecoders` and `RootDescriptor` do.
