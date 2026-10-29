# Read PHPDoc with a built-in parser instead of phpstan/phpdoc-parser

The package has no runtime dependencies besides PHP extensions. Users who run PHPStan often have phpstan/phpdoc-parser
in their projects through other tools, so our requirement would have to agree with theirs, and each major release of
it would block their upgrade until we release too. So the decoder reads doc comments with its own parser
(`src/Internal/PhpDoc*.php`), which follows the grammar of phpstan/phpdoc-parser exactly. Otherwise a class could pass
PHPStan and still decode differently from what PHPStan reports.

## Considered options

- **phpstan/phpdoc-parser as a runtime dependency.** On 2026-10-13 it was measured next to the built-in parser, on
  the first decode in a fresh PHP-FPM request with OPcache on. Without a fast path, it was +50% against `tabula-rasa`
  and the built-in parser +30%. With the same memo and fast path, it reached +0.0% and the built-in parser +1.9%. The
  built-in parser reached −0.7% only with two prechecks that phpdoc-parser can't take without a patch. So speed did
  not decide it: the choice was the dependency against about 2,300 more lines, and the maintainer chose to avoid the
  dependency.

## Consequences

- phpstan/phpdoc-parser is a development dependency, pinned to the version the parser follows. The `parity` test
  suite compares both, see [PHPDoc parity](../../CONTRIBUTING.md#phpdoc-parity). Upgrade the pin and the parser
  together.
- The fast path for simple doc comments (`PhpDocSimpleTags`) is needed. Without it, every doc comment goes through
  the full parser, and the first decode in a fresh PHP-FPM request was 10–34% slower
  ([#79](https://github.com/eventjet/eventjet-json/pull/79), closed for that reason). The parity suite also checks
  that the fast path reads the same tags as the full parser.
- The parser parses tags the decoder ignores, such as `@method` and Doctrine annotations, because they decide where
  the next tag starts.
- The parser is split into many small classes to stay within Mago's complexity limits.
- The only intended differences from phpdoc-parser are listed in [Reading PHPDoc](../types.md#reading-phpdoc).
