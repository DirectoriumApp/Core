# Edition governance

_Status: **implemented for the Novus Ordo 2002 (#366)**; the axis is reserved for later
editions. How **editions**, **snapshots**, **overlays**, and **decrees** relate on the
edition axis — the model that lets the living Novus Ordo calendar coexist with the static
traditional editions without either breaking the frozen output contract. Reuses the corpus
edition layout ([`corpus-schema.md`](corpus-schema.md)), the particular-calendar overlay
mechanics (Epic #75), and the rite-unit URNs (comparison Epic #299). The implementation is
in `src/Decree/`; see [The dated-decree engine](#the-dated-decree-engine-366) below._

## Static editions vs the living calendar

Two kinds of thing sit on the edition axis, and conflating them is the trap this
document exists to avoid:

- A **static edition** is a frozen rubric family — `roman:rubricae-1960`, and the
  reserved Divino Afflatu / 1954 / 1955 / Tridentine editions. Its rules and
  calendar do not change; a fix is an *erratum*, not a new state of the edition.
- The **Novus Ordo is a living calendar**: not one edition but a *stream* of
  editio typica **snapshots** (1969, 1975, 2002, 2008), plus ongoing **decrees**
  (new saints, rank changes, suppressions) and **national-conference propers**.
  The calendar for a given date depends on *which snapshot* and *which decrees
  were in force then*.

Modelling the NO as a single mutable "edition" would make its output
non-reproducible — the same `(date, edition)` would resolve differently as
decrees accrued. Instead the living calendar is decomposed into an ordered,
dated stack of immutable pieces.

## Snapshots

A **snapshot** is a named editio typica, and it is **named in the edition URN**:
`roman:novus-ordo-2002` is a distinct, resolvable edition from
`roman:novus-ordo-1969`. Each snapshot:

- resolves **independently** — pinning a snapshot gives a reproducible calendar
  for any date, with no dependence on later editions;
- is **diffable** against any other snapshot (or against a traditional edition)
  through the ordinary edition-comparison machinery — the whole point of naming it
  in the URN.

A snapshot is as static as `roman:rubricae-1960`; the living-ness of the NO is
expressed by *having several snapshots plus decrees*, not by mutating one.

## Decrees

A **decree** is a dated change published between snapshots (a canonisation, a
rank elevation, a suppression). Decrees are **dated overlay files**:

```
editions/<edition>/decrees/<effective-date>-<slug>.yaml
```

- Each decree carries an **effective date** and is **applied on or after** that
  date, so resolving a date replays exactly the decrees in force by then — the
  calendar reproducibly reflects its historical state.
- Decrees **reuse the particular-calendar overlay mechanics** (Epic #75): a
  decree is an additive/subtractive layer over the snapshot, applied by the same
  overlay engine, not a special case.

Because a decree is dated and additive, adding one never rewrites a snapshot and
never touches the output contract.

## The dated-decree engine (#366)

The model above is implemented in `src/Decree/`. A decree is authored as a cited YAML file
`facts/editions/<edition>/decrees/<effective-date>-<slug>.yaml`, compiled by the generator
into one row of `editions/<edition>/decrees.ndjson` and read back as a {@link Decree} value
object. A decree carries two kinds of change, either possibly empty:

- **Fixed-date sanctoral operations** reuse the particular-calendar overlay vocabulary
  verbatim — the same `add` / `rerank` / `suppress` rows, rebuilt through the shared
  `OverlayOperationFactory`. They are applied by a year-aware decorator, `DecreedSanctoralData`,
  which wraps the edition's base sanctoral for one resolution year and applies each operation
  only when the feast it targets falls, that year, **on or after** the decree's effective date.
  The gate is exact to the day: raising St Mary Magdalene (22 July) by the 2016 decree reads a
  memorial in 2015 and a feast from 2016.
- **Movable additions** are memorials placed by an Easter offset that no fixed month/day entry
  can express — the reform's Blessed Virgin Mary, Mother of the Church on the Monday after
  Pentecost (Easter + 50). A `MovableDecree` realizes such an office onto its date for a year;
  `DecreeSet::officesFor()` keeps only those whose date falls on or after the decree's effective
  date, and the resolver gathers them as ordinary candidates (`DecreeOffices`). It is celebrated
  on the free green weekday it normally falls on; the precedence *rule* for the rare years where
  that Monday coincides with a fixed obligatory memorial is a deferred refinement (see
  [`KNOWN-LIMITATIONS.md`](../../KNOWN-LIMITATIONS.md)).

`DecreeSet::forEdition()` loads a snapshot's decrees; the resolver applies them inside its
per-year sweep. An edition with no decree file loads an **empty set**, and both application
paths are then inert — the traditional editions and a Novus-Ordo year before its first decree
resolve byte-identically to a decree-free engine, so the 1962 golden fixture is unmoved by
construction. Decrees change resolved *values* (a rank, an added office), never the contract
*shape*, so no `SHAPE_VERSION` bump attends them. The two decrees shipped with #366 are
*Apostolorum Apostola* (2016, Magdalene → feast) and *Ecclesia Mater* (2018, Mother of the
Church); each is cited to its promulgating Congregation-for-Divine-Worship decree, never
transcribing the decree's text.

## Overlays are not editions

A **particular calendar** — SSPX, FSSP, a diocese — and a **conference propers**
set are **`overlay`-type layers** ([`platform-urn-scheme.md`](platform-urn-scheme.md)),
applied *atop* an edition or a snapshot. They add, remove, or re-rank observances;
they do **not** restate the rubric family. An overlay is therefore **never an
edition**: `roman:rubricae-1960` is an edition, `directorium:overlay:roman:sspx` is a
layer resolved over it. The same overlay engine that applies a diocesan calendar
applies a decree — they are the same mechanism at different cadences.

**Conference-propers hook (reserved).** A national or regional conference's proper
calendar — the US, England-and-Wales, or a diocesan supplement to the General Roman
Calendar — is exactly such an `overlay`-type layer, resolved *over* a snapshot the same
way a particular calendar is, and (where it has date-effective national decrees) carrying
its own dated stack. The mechanism it needs already exists: the `CalendarOverlay` /
`OverlaidSanctoralData` seam for a user-selected conference layer, and the `DecreeSet` seam
(#366) for its dated national decrees. A conference propers set is authored as an overlay
slug (`overlays/<conference>/`) selected through the `$calendar` axis and unioned onto the
national sanctoral by id exactly as SSPX/FSSP/ICKSP are — its build is deferred (its data,
not the engine, is the work), but the hook is the shipped overlay + decree machinery, not a
new subsystem.

## The edition validity window

Each edition carries a **validity window** and a **supersession** pointer — on the
`RubricSystem` value object today (`src/Edition/RubricSystem.php`), the natural home while the
axis is a small fixed registry (a corpus `edition.json` per [`corpus-schema.md`](corpus-schema.md)
is the reserved shape should the registry ever need to be data-driven):

- the **validity window** (`validFrom()` / `validTo()`, and the `governs(year)` predicate) is
  the historical span over which the edition governed — 1954 governed 1913–55, 1955 governed
  1956–60, 1962 is open-ended, the 2002 Novus Ordo governs 2002 onward;
- **supersession** (`supersededBy()`) names the edition that replaced it, within its own line
  — 1954 → 1955 → 1962, and 1969 → 1975 → 2002. 1962 and the 2002 Novus Ordo are each the
  living head of their line (`null`): the reform opened a *parallel* line, it did not supersede
  1962, and 2002 is kept living by dated decrees rather than by a new typica.

Resolving a date **outside** an edition's window is anachronistic; `governs()` reports it, so a
caller (the Api `/meta`, a UI) can flag it. Surfacing that flag *inside the day contract* is
deferred — it would be an additive contract field, held until it is needed rather than added
speculatively (consistent with [`KNOWN-LIMITATIONS.md`](../../KNOWN-LIMITATIONS.md)); the
resolver still returns a well-formed day for an out-of-window date.

## The 1965/67 interim rite

The **1965/67 interim rite** is an **edition on this axis** (reserved). Naming
its slot now — rather than discovering it later — means its eventual insertion is
**additive**: a new edition record and its attributes, not a breaking change to a
frozen edition grammar or output contract. The same reservation applies to any
future edition; the axis is designed to grow by addition.

## Freezing the axis with the rite-units

The snapshot/decree axis is cross-referenced to the **rite-unit URNs** (the
comparison Epic #299) so the edition grammar **freezes with the axis already in
place**: a `rite-unit` is resolved under `(edition-or-snapshot, decrees-in-force,
overlays)`, exactly as an observance is. Fixing that here means the comparison
tool can diff a rite unit across snapshots and overlays without a later grammar
change — the edition axis and the rite-unit axis are designed together.
