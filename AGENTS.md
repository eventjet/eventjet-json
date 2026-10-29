# Repository principles

- Treat `Json::decode()` as the precise inverse of `json_encode()`. Any supported value must round-trip without changing its meaning or shape.
- Aim to test as much behavior as possible, ideally everything, with property-based testing. Express general laws over clearly defined input domains and use generated inputs where practical. Keep focused examples for boundaries, regressions, and exact error contracts when they add coverage or clarity.
- Extend acceptance coverage through the existing `test*` methods and data providers wherever possible. Get explicit user approval before adding or changing a `test*` method in `AcceptanceTest` or creating a new `*Test` class. New data providers do not require approval.
- Check `docs/adr/` before proposing to reverse a design choice, such as removing a fast path or the built-in PHPDoc parser. Each record holds a decision and the measurements behind it; reversing one needs new evidence, such as a fresh benchmark. Record new decisions of that kind there.
- Keep target classes independent of this package whenever standard PHP conventions can express the required type information. Prefer broadly understood declarations, such as `list<Foo>` in a docblock, over attributes or annotations defined only for this library.
