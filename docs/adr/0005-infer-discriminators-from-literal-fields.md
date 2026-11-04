# Infer discriminators from literal fields

A union of several final classes, such as `Circle|Rectangle`, decodes when every class declares a member with distinct
string, int, or backed enum case literal types, such as `/** @var 'circle' */ public string $type`. The decoder infers
that member while it compiles the union (decided on 2026-11-02). When more than one member qualifies, the field that
holds the union names the JSON member with `#[Discriminator('type')]`. The decoder still works out which value selects
which class. [Types](../types.md#discriminated-unions) documents the rules for users.

Inference comes first because the classes already say everything the decoder needs. A literal-typed member is plain
PHP that `json_encode()` writes back, and PHPStan and Psalm narrow `$shape->type === 'circle'` to `Circle`. So the
classes stay independent of this package, as the repository principles ask. The attribute is the escape hatch for
classes where inference cannot choose. It goes on the field, not on the classes, because the same classes may appear
in unions that need different members.

## Considered options

- **Structural matching: choose the class whose fields fit the object.** This was rejected. A union may have only one
  JSON-object member, so that each JSON value selects one member without trying others; structural matching would need
  to try every class and keep the one that fits. Objects that fit several classes, or none, would give poor error
  messages: the decoder could not say which class the input meant, so it could not say which field was wrong.
- **An explicit tag-to-class mapping, such as `#[Discriminator('type', ['circle' => Circle::class])]`.** This was
  rejected because the mapping repeats what the classes already declare and can drift from them. It would also make
  the target classes depend on an attribute of this package for information that standard PHPDoc can express.
- **A class-level attribute that names each class's tag.** This was rejected for the same reasons as the mapping.

## Consequences

- Inference reads only the PHPDoc of the member classes. It compiles no class decoders, so recursive unions, such as an
  `AndFilter` that holds `list<AndFilter|OrFilter|Equals>`, compile like any other recursive class graph.
- The compiled union holds a lookup table from each tag to its class. A decode costs one member read and one lookup
  before the chosen class decodes the object, including the tag, through its usual path.
- Unions with at most one class, and fields without `#[Discriminator]`, load none of the new classes, so their first
  decode in a request does not get slower. Unit tests under `tests/Unit/Optimization` pin this.
- `true`, `false`, floats, `null`, and whole enums stay unsupported as tags. Supporting them later, or adding a root
  union factory, is not a breaking change, because it only turns errors into successful decodes.
