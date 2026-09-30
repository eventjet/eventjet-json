# Repository principles

- Treat `Json::decode()` as the precise inverse of `json_encode()`. Any supported value must round-trip without changing its meaning or shape.
- Keep target classes independent of this package whenever standard PHP conventions can express the required type information. Prefer broadly understood declarations, such as `list<Foo>` in a docblock, over attributes or annotations defined only for this library.
