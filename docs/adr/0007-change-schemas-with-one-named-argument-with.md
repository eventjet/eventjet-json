# Change schemas with one `with()` that takes named arguments

The schema classes are readonly, and changing a schema should not take that away. So each draft class has one `with()`
whose parameters match the constructor's, each with the default `Keep::Current` (decided on 2026-11-05). Static
analysis checks each argument against the same type as the constructor parameter, PHPDoc types included, and one call
can change several keywords. `null` already means that a keyword is absent, so it can't also mean "unchanged". That is
why the default is a separate enum. The enum is public, so callers can pass it too, as in
`$schema->with(title: $title ?? Keep::Current)`.

## Static analysis of PHP 8.5 `clone with`

Measured on 2026-11-05 with a class that has `public(set) readonly` properties and the PHPDoc types of `Draft202012`,
analyzed as PHP 8.5:

| Case | PHPStan 2.3.5 | Psalm 6.20.9 | Mago 1.59.0 |
|---|---|---|---|
| Wrong native type: `['title' => 1]` | caught | caught | missed |
| Wrong list items: `['allOf' => [1, 2]]` | caught | caught | missed |
| Not a list: `['allOf' => ['a' => true]]` | caught | missed | missed |
| Wrong `ArrayObject` items | caught | missed | missed |
| Misspelled name: `['tittle' => 'x']` | caught | caught | missed |
| Property without `public(set)` | caught | missed | missed |

Passed to the constructor as named arguments, the same values are caught by all three tools. Psalm misses a write to a
property without `public(set)` even outside `clone with`.

## Considered options

- **One wither per keyword**, such as `withTitle()`. Static analysis checks it as well, but each class gets 35 to 59
  methods, and changing several keywords makes a copy for each.
- **`with('title', $value)`.** Static analysis can't check the value against the keyword's type, so it would need a
  runtime check, and PHP checks only native types such as `array`, not `list<self|bool>`.
- **One wither per value type**, such as `withSchema('not', $schema)`. The keyword is a string again, and the groups
  don't match the PHPDoc types of the keywords.
- **PHP 8.5 `clone($schema, ['title' => 'x'])`.** It needs no methods, but it requires PHP 8.5, while the package
  supports 8.4, and `public(set)` on every property. Psalm and Mago don't check it fully (see above).
- **Mutable classes that the readonly classes extend, or the reverse.** PHP doesn't allow a readonly class and a
  non-readonly class to extend each other, or a child class to redeclare a readonly property as non-readonly.
- **Mutable twin classes**, converted to and from the readonly ones, possibly with a shared interface of `{ get; }`
  properties. This doubles the classes for each draft, and converting has to walk the nested schemas.

## Consequences

- A new keyword needs a parameter in both the constructor and `with()`. The unit tests compare `with()` with the
  constructor for every keyword, so a missing parameter fails them.
- `with()` has as many parameters as the constructor, so it carries the same Mago suppression for the parameter list.
  In `Draft201909` and `Draft202012`, it also exceeds Mago's Halstead volume limit.
- The copy shares nested schemas and `ArrayObject` maps with the original. `readonly` doesn't stop changes to a map in
  place, so changing a map changes it in both.
