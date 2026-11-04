# Reject repeated names when encoding other members

A property marked with `#[OtherMembers]` collects the members that no declared field claims. An object built by hand can
hold a collected member whose name is the JSON name of a declared field. Decoding the encoded object would bind that
member to the declared field, so the round trip would change the object. `MappedJsonFields` could either throw for such
an object or let the declared field win silently. The decision was to throw if a benchmark shows that the check is
basically free (decided on 2026-11-03).

`MappedJsonFields` throws an `UnexpectedValueException`. The check also covers declared fields that the serializer
leaves out, such as a `JsonValue|null` property that is `null` and an uninitialized property, because decoding would
bind a member of their name to them too. Constructors don't check, and decoded objects never hold such a member.

## Measurement

`tests/Performance/OtherMembersBench.php`, warm `json_encode()` of a class with three fields, 20,000 revolutions and 15
iterations, OPcache on, three alternating runs per variant, measured locally on 2026-11-03. "Without the check" writes
`$declared + $others`, so the declared field wins.

| Collected members | Without the check | With the check | Change |
|---|---:|---:|---:|
| none | 0.543–0.545 µs | 0.555–0.559 µs | +0.012 µs, +2.2% |
| three | 0.885–0.890 µs | 0.927–0.933 µs | +0.043 µs, +4.9% |

The check looks up each collected member in the JSON names of the declared fields, which the class's cached field
metadata already holds, so it costs about 15 ns per collected member. Classes without `#[OtherMembers]` don't run it.

This counts as basically free for three reasons. Only classes that opt in with `#[OtherMembers]` pay, and only when they
encode; decoding, which the performance workflow guards with a 3% limit, doesn't run the check. Without collected
members, the cost is a fixed 12 ns per encode, +2.2%, which stays within that limit. With collected members, it grows
with their number, at about 15 ns each, an eighth of the 115 ns that writing each member costs without the check.

## Considered options

- **Let the declared field win silently.** It costs nothing, but writes a document that doesn't decode back to the
  object, without any sign of the problem.
- **Count instead of looking up names.** Comparing `count($declared + $others)` with the sum of both counts found a
  repeated name for +8% with three members, because it builds another array. Counting only the written fields cost
  +3%, but misses fields that the serializer leaves out, and adding a slower check just for those is a fast path that
  no test can tell apart from the general check.
- **Check in the constructors of users' classes.** Rejected because users' classes don't depend on this package.

## Consequences

`MappedJsonFields::jsonSerialize()` declares `@throws UnexpectedValueException`. Classes with their own
`jsonSerialize()` are responsible for the same rule.
