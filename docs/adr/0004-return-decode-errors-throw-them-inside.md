# Return decode errors from the public API, and throw them inside

`Json::decode()` and `Json::validateType()` never throw. They return the decoded value or a `DecodeError`, the way Go
and Rust return errors as values, so callers have to handle failure (decided on 2026-09-30).

Inside the library, errors are thrown, and the public API catches them. A `try` block costs nothing until something is
thrown, so this made cold decodes 1.5–4.7% faster than returning errors through every call
([#82](https://github.com/eventjet/eventjet-json/pull/82)).

## Consequences

`ClassDecoders::hydrate()` throws the error of a nested object, but `ClassDecoders::decodeRoot()` returns the error of
the root object instead of throwing it once more. Each extra throw costs about 35 ns, which made an unknown enum value
in a tiny document 6.5% slower.
