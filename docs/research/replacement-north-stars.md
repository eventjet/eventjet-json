# Replacement north-star JSON documents

## Recommendation

Use these two replacements:

1. **JSON:API 1.1 compound document** — it directly requires an array-bearing union (`ResourceIdentifier|list<ResourceIdentifier>|null`) and typed relationship maps.
2. **AWS Lambda Amazon MSK event** — it directly requires maps whose values are lists and nested list/map/list combinations. AWS's own event library uses those exact types.

Both are producer-owned, public examples of formats used at real integration boundaries. Neither failure depends on adding a stricter type than the format itself defines. Together they cover the two most important open collection capabilities without duplicating the remaining Stripe invoice.

The current implementation basis for every failure below is the repository's [supported-type matrix and TODO list](../../README.md#supported-types-and-limits): only simple `list<T>` PHPDoc declarations are interpreted; typed maps, recursively nested collections, tuples, array-shaped union members, and unions inside collections are not yet supported.

## Ranked candidates

### 1. JSON:API 1.1 compound document

- **Exact document:** the complete `articles` compound document under [JSON:API 1.1, “Compound Documents”](https://jsonapi.org/format/1.1/#document-compound-documents). This is an archived, immutable copy of version 1.1; the page states that its normative text will not change.
- **Why it is real and representative:** JSON:API defines the registered `application/vnd.api+json` media type and how clients and servers exchange resource documents. The example contains primary resources, included resources, to-one linkage, and to-many linkage. The specification requires relationship linkage to be `null`, one resource identifier object, or an array of resource identifier objects ([“Resource Linkage”](https://jsonapi.org/format/1.1/#document-resource-object-linkage)).
- **Natural PHP shapes:** `JsonApiDocument::$data` is `Resource|list<Resource>|null`; `Resource::$relationships` is `array<string, Relationship>`; and `Relationship::$data` is `ResourceIdentifier|list<ResourceIdentifier>|null`. For this exact document, `Resource::$attributes` can be `array<string, string>` rather than an untyped bag.
- **Why it fails today:** the decoder does not support unions with array-shaped members or typed maps. The `author` relationship exercises the object branch while `comments` exercises the list branch in the same official document. Ignoring that distinction or leaving the fields untyped would defeat the format's declared relationship semantics rather than produce full type-safe hydration.
- **Roadmap coverage:** typed maps; unions with array-shaped members; nested combinations of maps, objects, and lists.

### 2. AWS Lambda Amazon MSK event

- **Exact document:** the JSON payload under [AWS Lambda, “Using Lambda with Amazon MSK — Example event”](https://docs.aws.amazon.com/lambda/latest/dg/with-msk.html). The code block is valid JSON and includes one topic-partition entry and one Kafka header.
- **Why it is real and representative:** AWS documents this as the event that Lambda sends when Amazon MSK invokes a function. AWS's first-party Go event package independently defines `KafkaEvent.Records` as `map[string][]KafkaRecord` and `KafkaRecord.Headers` as `[]map[string]JSONNumberBytes` in [`events/kafka.go`](https://github.com/aws/aws-lambda-go/blob/main/events/kafka.go).
- **Natural PHP shapes:** `MskEvent::$records` is `array<string, list<KafkaRecord>>`; `KafkaRecord::$headers` is `list<array<string, list<int>>>`; the remaining members are scalars.
- **Why it fails today:** the outer `records` value is a typed map whose values are lists, and each header is a list containing a map whose values are integer lists. The current decoder reads neither typed maps nor arbitrary nesting of different collection shapes. These types come directly from AWS's event model, so the failure is not caused by an artificial PHP design.
- **Roadmap coverage:** typed maps; recursively nested lists and maps; arbitrary nesting such as `array<string, list<Foo>>` and `list<array<string, list<int>>>`.

### 3. OCI image configuration

- **Exact document:** the full image configuration under [OCI Image Specification v1.1.1, `config.md` — “Example”](https://github.com/opencontainers/image-spec/blob/v1.1.1/config.md#example). The version tag makes this location stable.
- **Why it is real and representative:** this is the canonical configuration document defined by the Open Container Initiative image format. The same specification defines `ExposedPorts` and `Volumes` as maps whose keys carry the information and whose values are empty objects, while `Labels` is a string-to-string map ([OCI Image Specification v1.1.1 configuration properties](https://github.com/opencontainers/image-spec/blob/v1.1.1/config.md)).
- **Natural PHP shapes:** `Config::$ExposedPorts` and `Config::$Volumes` are `array<string, EmptyObject>`; `Config::$Labels` is `array<string, string>`; `ImageConfiguration::$history` is `list<HistoryEntry>`.
- **Why it fails today:** typed maps are unsupported. More importantly, using an untyped PHP array is not an adequate escape hatch: the example's map values are `{}`, and current array normalization cannot preserve the distinction between `{}` and `[]`. An exact round trip therefore needs both typed map values and empty-object preservation.
- **Roadmap coverage:** typed maps with object and scalar values; empty object versus empty array preservation; nested objects and object lists.

### 4. AWS DynamoDB Streams records

- **Exact document:** the JSON output under [AWS CLI, “DynamoDB Streams examples — Get records”](https://docs.aws.amazon.com/cli/latest/userguide/cli_dynamodb-streams_code_examples.html). It contains multiple stream records with `Keys` and `NewImage` objects.
- **Why it is real and representative:** this is first-party AWS CLI output. The DynamoDB Streams API defines `Keys`, `NewImage`, and `OldImage` as string-to-`AttributeValue` maps ([`StreamRecord`](https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_streams_StreamRecord.html)). The `AttributeValue` API includes scalar alternatives, scalar sets, a list of `AttributeValue`, and a string-to-`AttributeValue` map ([`AttributeValue`](https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_AttributeValue.html)).
- **Natural PHP shapes:** `Records` is `list<DynamoDbStreamRecord>`; `Keys`, `NewImage`, and `OldImage` are `array<string, AttributeValue>`; `AttributeValue::$L` is `list<AttributeValue>` and `AttributeValue::$M` is `array<string, AttributeValue>`, with nullable scalar and scalar-list alternatives for the other DynamoDB value kinds.
- **Why it fails today:** the official sample immediately needs typed maps of objects. A complete `AttributeValue` model also recursively nests those maps and lists. Leaving the image fields untyped would lose validation of DynamoDB's tagged value representation.
- **Roadmap coverage:** typed maps with object values; recursive maps and lists; nullable alternatives; enum validation for event and stream-view names.

### 5. npm CLI v12.1.0 `package-lock.json`

- **Exact document:** npm CLI's own versioned [`package-lock.json` at v12.1.0](https://github.com/npm/cli/blob/v12.1.0/package-lock.json). The tag is an npm release, so this is a stable snapshot rather than a moving example.
- **Why it is real and representative:** this is the lockfile used to build npm itself. npm's official lockfile documentation defines the `packages` member as an object whose keys are package locations and whose values carry package information ([npm `package-lock.json` documentation](https://docs.npmjs.com/files/package-lock.json/)). The official repository identifies npm as the JavaScript package manager and keeps this generated lockfile at its root ([npm CLI repository](https://github.com/npm/cli/tree/v12.1.0)).
- **Natural PHP shapes:** `PackageLock::$packages` is `array<string, LockedPackage>`; each `LockedPackage` contains typed maps such as dependency name to version range, plus nested string-or-list and scalar collections for package metadata. The root key `""` maps to the workspace package, while `node_modules/...` keys map to installed packages.
- **Why it fails today:** values of `packages` are objects that must become `LockedPackage` instances. With no typed-map conversion, they remain generic array values, so a hydration check fails immediately. Exact round-trip coverage then exposes further unsupported map and nested collection value shapes in the package entries. Modeling `packages` as an untyped array would discard the package-entry type that the lockfile format assigns to every value.
- **Roadmap coverage:** large typed maps of objects; maps of scalar values; nested collection unions; deep, realistic document hydration.

## Selection notes

- **Best balanced pair:** JSON:API plus Amazon MSK. One makes array-bearing unions unavoidable; the other makes map/list nesting unavoidable.
- **Best exact-round-trip regression:** OCI image configuration. Its empty-object map values prove that an untyped-array workaround changes JSON shape.
- **Best recursive map target:** DynamoDB Streams. Its full `AttributeValue` model remains useful after basic typed maps land because `M` and `L` recurse.
- **Best scale target:** npm's own lockfile. It is much larger than the others and is useful once map support exists, but it overlaps the map capability exercised more compactly by Amazon MSK and DynamoDB.

## Attractive candidate that is not reliably red today

The [RFC 7946 section 1.5 GeoJSON FeatureCollection](https://www.rfc-editor.org/rfc/rfc7946.html#section-1.5) is an excellent future validation fixture for nested lists, coordinate tuples, and array-shaped unions. It is not a safe replacement north star yet: all unsupported coordinate members contain only arrays and scalars, so the current decoder can pass them through as untyped arrays and may already reproduce the example byte-for-byte after canonicalization. A north star must fail on hydration or shape today, not merely rely on a PHPDoc declaration that the decoder currently ignores.
