# API stability & versioning

Directorium Core is versioned so downstream platforms (the Api, Site, and Ordo) can
build on it safely. This page defines what is **public**, what "stable" promises, and how
changes are versioned. The [output contract](design/output-contract.md) has its own,
compatible field-level rules; this page covers the **PHP API** and ties the two together.

## What is public

The public surface — the only things a consumer may depend on — is enumerated and
machine-checked in
[`tests/Api/public-api-surface.txt`](../tests/Api/public-api-surface.txt), pinned by
`PublicApiSnapshotTest`. It is:

- the entry functions `Directorium\Core\day()`, `contract()`, `explain()`;
- the resolved-day objects and the value objects they expose (`Calendar\LiturgicalDay`,
  the `Calendar\RealizedObservance` interface, `Attribute\RankClass`,
  `Attribute\ElementColour`, `Attribute\Colour`, `Observance\ObservanceId`,
  `Observance\ObservanceKind`, `Temporal\Season`, `Temporal\SeasonVocabulary`);
- the contract serialiser and its descriptor (`Contract\DayContract`,
  `Contract\CalendarDescriptor`);
- the selectors' registries (`Edition\RubricSystem`; `Overlay\CalendarCatalog`, whose
  consumer-facing methods are `descriptor()` and `particularCalendars()` for overlay
  discovery);
- the calendar-mode comparison classes (`Compare\CalendarComparator`,
  `Compare\SequenceComparator`, and their result value objects) — but **not**
  `Compare\EditionResolver`, which is an internal helper;
- `Corpus\Corpus` (the corpus root + the `flush()` cache-invalidation hook) and
  `Directorium` (the `NAME` and `VERSION` constants).

**Everything else under `Directorium\Core\` is internal implementation detail** — the
precedence engine, the temporal cycles, the sanctoral loaders, the resolution-trace
internals — and is **not** covered by the guarantees below. It may change in any release.
Internal classes are marked `@internal`; the snapshot above is the authoritative list of
what is *not*.

Because PHP has no package-private visibility, a few frozen signatures **name** internal
types in passing — the resolved-object construction graph (`Contract\Provenance`,
`Precedence\ConcurrenceOutcome`, `Trace\ResolutionTrace`, `Discipline\FastingObligation`,
and `Overlay\CalendarCatalog::resolver()`'s internal `Precedence\DayResolver`). Those
types are `@internal` and **not** part of the compatibility contract: renaming one trips
the snapshot and must be re-frozen, but that is a mechanical update, **not** a breaking
change. Build on the `contract()` array (fully shape-frozen) and the documented value
objects, never on these types.

## Versioning

The package follows semantic versioning, and the code API and the output contract move
together:

- **Patch** (`1.0.x`) — bug fixes and internal changes; no observable API or contract
  change. (A corrected liturgical fact is an *erratum*, tracked in `ERRATA.md`, and
  changes resolved data, not the shape.)
- **Minor** (`1.x.0`) — **additive** only: a new public function/class/method, a new
  optional parameter with a default, a new contract field, or a new member of an **open**
  enum. Existing code keeps working.
- **Major** (`2.0.0`) — anything **breaking**: removing or renaming a public member,
  changing a signature incompatibly, removing or repurposing a contract field, or adding
  a member to a **closed** enum. **Frozen: no breaking change before 2.0.**

The two snapshot guards make an accidental break impossible to merge unnoticed:
`PublicApiSnapshotTest` (the PHP surface) and `ContractShapeTest` + the golden-year digest
(the contract shape and its resolved values). An intended additive change re-freezes the
relevant snapshot in the same reviewed commit; a change that would *remove* or *alter* a
member is a major-version decision.

## Deprecation

Nothing public is removed without a deprecation first. To retire a member: mark it
`@deprecated` (with the replacement) in a minor release, keep it working for the rest of
the 1.x line, and remove it only at the next major. Identifiers and URNs are never
re-homed — lineage lives in the contract's reserved `aliases` slot, never by mutating an
id.

## 0.x → 1.0 migration

The 1.0 line is shape-compatible with late 0.x; the only pre-freeze change of note:

- **`season` was reclassified from a closed enum to an open, edition-scoped vocabulary**
  (#364, [season-vocabulary.md](design/season-vocabulary.md)). The 1962/1954/1955 token
  sets are unchanged, so resolved output is byte-identical; the change is that a *future*
  edition adding a season (the Novus Ordo's `ordinary-time`) is a minor bump rather than
  a breaking one.

No fields were removed or renamed; all other 0.x → 1.0 changes were additive (the
`calendar` particular block, the calendrical and fasting blocks, the `resolution` trace
slot), each a reserved slot filled.
