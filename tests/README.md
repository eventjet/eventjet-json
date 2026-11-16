# Tests

Every test drives the library through its public API: `Json::decode()`, `Json::validateType()`, `JsonType`, the
attributes, `MappedJsonFields`, and the JSON Schema classes. A test does not call internal classes, inspect caches,
or count how often internal code runs. If a behavior cannot be observed through the public API, it is not a contract
and does not need a test.

Each test method states a law, and data providers supply the cases. The laws are general, such as "every decodable
document decodes to the expected value" or "`validateType()` agrees with `decode()`", so most new behavior needs a new
case, not a new method.

## Adding a test

1. Add a provider case to an existing law. Look for the law in the list below, then add the case to its data provider
   or to one of the case classes that the provider reads.
2. Add a new `test*` method only when no existing law can express the behavior. Explain in the pull request why the
   new law belongs to the public API, and update this file in the same change.

## Entry points

### `tests/Acceptance`

| Entry point | Drives |
| --- | --- |
| `AcceptanceTest::testDecodeSucceeds` | Public API. `Json::decode()` returns the expected value for every supported document, and lossless cases encode back to the same JSON. Every case decodes twice, so results do not depend on a cold or warm cache. |
| `AcceptanceTest::testDecodeFails` | Public API. `Json::decode()` returns a `DecodeError` with the exact message, kind, code `0`, and previous exception, and never one that wraps another `DecodeError`. Every case decodes twice. |
| `AcceptanceTest::testValidateTypeAcceptsEveryDecodableType` | Public API. `Json::validateType()` accepts the type of every success case. |
| `AcceptanceTest::testValidateTypeAgreesWithDecodeFailures` | Public API. `Json::validateType()` reports a decode failure's message when its kind is `UnsupportedDeclaration`, and nothing otherwise. `ValidationOverrides` lists the exceptions. |
| `AcceptanceTest::testDecodingLoadsOnlyWhatItNeeds` | Public API, observed through autoloading. `Json::decode()` loads only the classes that the declarations and values need. Runs in a separate process. |

### `tests/Unit/Schema`

| Entry point | Drives |
| --- | --- |
| `DraftTest::testWithChangesOnlyTheGivenKeywords` | Public API. `with()` on the JSON Schema classes changes only the given keywords. |
| `DraftTest::testWithTakesTheConstructorParametersOrKeep` | Public API, observed through reflection. `with()` declares every constructor parameter with the same native and PHPDoc types plus `Keep`, and defaults to `Keep::Current`. Static analysis and PHP don't notice when the two declarations drift apart. |

### `tests/Parity`

These run in the `parity` test suite, not the default one.

| Entry point | Drives |
| --- | --- |
| `PhpDocFastPathTest::testFastPathReadsTheSameTagsAsTheFullParser` | Internal. The doc comment fast path reads the same tags as the full parser. |
| `PhpDocParserParityTest::testDocCommentsAndTypesReadLikePhpStan` | Internal. The built-in PHPDoc parser reads generated doc comments like PHPStan. |
| `PhpDocParserParityTest::testTestSuiteFieldsResolveLikePhpStan` | Internal. The built-in PHPDoc parser reads every field in the test suite like PHPStan. |
