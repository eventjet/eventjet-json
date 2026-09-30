# Repository principles

- Treat `Json::decode()` as the precise inverse of `json_encode()`. Any supported value must round-trip without changing its meaning or shape.
- Aim to test as much behavior as possible, ideally everything, with property-based testing. Express general laws over clearly defined input domains and use generated inputs where practical. Keep focused examples for boundaries, regressions, and exact error contracts when they add coverage or clarity.
- Keep target classes independent of this package whenever standard PHP conventions can express the required type information. Prefer broadly understood declarations, such as `list<Foo>` in a docblock, over attributes or annotations defined only for this library.
