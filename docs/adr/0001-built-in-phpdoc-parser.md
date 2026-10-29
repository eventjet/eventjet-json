# Read PHPDoc with a built-in parser instead of phpstan/phpdoc-parser

The package has no runtime dependencies besides PHP extensions, because each one would become part of every
consumer's dependency resolution. So the decoder reads doc comments with its own parser
(`src/Internal/PhpDoc*.php`), which follows the grammar of phpstan/phpdoc-parser exactly. Otherwise a class could pass
PHPStan and still decode differently from what PHPStan reports.

## Considered options

- **phpstan/phpdoc-parser as a runtime dependency.** On 2026-10-13 it was measured next to the built-in parser. It
  needed about 2,400 fewer lines, but the maintainer chose the built-in parser to keep the package free of runtime
  dependencies. Its first decode in a fresh PHP-FPM request was also slower at the time: +45% against +25% for the
  built-in parser, before either had a fast path.

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
