# Search a doc comment once for HTML end tags

phpdoc-parser reads `<b>` after a type name as an HTML element, not as generic arguments, if an end tag such as `</b>`
follows anywhere later in the doc comment. `PhpDocHtmlDetector` used to walk to the end of the doc comment for every
`<identifier>` after a type name, such as the `<int>` in `list<int>`. phpdoc-parser 2.4.0 avoids that walk
([phpstan/phpdoc-parser@3fcdcf0](https://github.com/phpstan/phpdoc-parser/commit/3fcdcf0)): it searches the tokens once
for `/name>` and remembers the last token that contains it, for each name. So `PhpDocTokens::lastEndTag()` does the
same, and the detector walks only up to that token (decided on 2026-11-02). The result is the same as before, because
every end tag that the walk finds contains `/name>`.

The search only runs for doc comments that pass the existing check for a slash between two other characters, which no
doc comment in the benchmark documents does.

## Measurement

First decode in a fresh PHP-FPM request with OPcache on, 80 alternating pool rounds of 10 requests per revision,
measured locally on 2026-11-02 (baseline 2156abf, candidate 12577ea). An extra document, `documented order`, was
added for this measurement only: an order with five lines and discounts, built from three classes whose constructor
doc comments put a documentation URL after each `@param` and use `list<…>` for five, two and one of their parameters.

| Workload | Change |
|---|---:|
| documented order | −9.33% |
| JSON:API compound document | +0.80% |
| AWS Lambda Amazon MSK event | +0.26% |
| Stripe invoice | +1.27% |
| GitHub pull request webhook | +1.62% |
| Kubernetes deployment | −0.28% |
| record batch 0, 1, 100, 1000 | +0.20%, +0.90%, −0.88%, +0.76% |

The existing documents don't reach the changed code, so their changes are noise; all stay within the 3% limit.

## Consequences

No workload of the Performance workflow reaches the search, so the blocking check would not notice its removal. The
parity suite and the acceptance cases for HTML after a type name check that the result stays the same.
