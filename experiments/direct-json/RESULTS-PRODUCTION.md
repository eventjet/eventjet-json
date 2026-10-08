# Direct decoding integration

`Json::decode()` retains direct parsing for constructor-bound scalar records and
objects containing a single list of those records. The complete input must match
the schema before any constructor runs. Direct list parsing reads records one at
a time rather than allocating an intermediate `stdClass` tree.

Plans support nullable scalar types and literal booleans. Inputs use declaration
order, include every field, and contain unescaped ASCII string values. Integers
are limited to 18 digits on this path; larger values use native overflow handling.
JSON whitespace is accepted around every structural token.

Other schemas and inputs use the existing native decoder and hydration plans.
This includes mapped fields, enums, public properties, nested collections,
defaults, reordered or unknown fields, escaped strings, and non-ASCII strings.
Native hydration registers eligibility in its existing constructor-plan cache.
Only potential scalar or single-list schemas compile on a subsequent direct call.
The public decoder keeps native fallback inline and avoids a second cache lookup
for ineligible targets. Invalid JSON retains syntax-before-declaration error ordering.
Constructors observe the native JSON error state, and exceptions are returned
without constructing the object again.

Production code does not generate or evaluate PHP source. The independent native
reference lives in `experiments/direct-json/NativeJsonDecoder.php`. The previous
broad parser and its measurements remain in the experiment directory; see the
[historical report](RESULTS-BROAD-PARSER.md).

## Local paired measurements

PHP 8.4.26 on Linux, CLI OPcache enabled, PCRE JIT enabled, PHP JIT disabled.
Fifteen randomized paired rounds compare the warmed public decoder with the
native reference using the updated hydration implementation. Ratios below one
indicate faster direct decoding; these measurements do not replace the CI gate.

| Workload | Median paired runtime ratio |
| --- | ---: |
| Scalar object | 0.873 |
| 1,000-record batch | 0.791 |
| Stripe invoice | 1.014 |
| GitHub pull request webhook | 1.000 |
| Long scalar lists | 0.999 |
| Ignored tree | 0.998 |

Reproduce with `php -d opcache.enable_cli=1 experiments/direct-json/paired.php 15 native,production`.

## Validation

Validation against the updated target branch includes the full PHPUnit suite,
PHPStan, Psalm, Mago, dependency checks, and seeded differential verification.
Run `php experiments/direct-json/verify.php production` to compare the public
decoder with the native reference. The performance CI continues to enforce its
existing 5% regression threshold with OPcache both enabled and disabled.
