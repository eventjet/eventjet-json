# Write the JSON Schema validator in-house

The package validates values against JSON Schemas with its own validator (`src/Schema/Validator.php` and
`src/Internal/Schema/`), not with a validator library (decided on 2026-11-04,
[#124](https://github.com/eventjet/eventjet-json/issues/124)). The reasoning is the same as for the PHPDoc parser in
[ADR 0001](0001-built-in-phpdoc-parser.md): the package has no runtime dependencies besides PHP extensions, and a
dependency's version constraint would have to agree with whatever versions users' projects already use.

A library would also work on `json_decode()` output, not on the schema classes, so users would convert their schemas
back to plain objects first.

## Consequences

- The validator supports Draft 4, Draft 6, Draft 7, Draft 2019-09, and Draft 2020-12, the drafts that have schema
  classes, and passes every required and optional test of the official JSON Schema Test Suite. The suite is a Git
  submodule in `tests/JsonSchemaTestSuite`, pinned to a commit and updated by Dependabot, and
  `tests/Schema/OfficialTestSuiteTest.php` runs all of it.
- The official meta-schemas ship in `resources/meta-schemas/`, with their BSD license notice.
- Internally, the compiler works on the tree that `json_decode()` gives for a schema object's `json_encode()` output,
  not on the schema objects. That tree has the same shape for every draft, so `$ref` with a JSON Pointer can point
  anywhere in a document, including into unknown keywords.
