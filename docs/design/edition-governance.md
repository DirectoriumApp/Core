# Edition governance

_Status: reserved. How **editions**, **snapshots**, **overlays**, and **decrees**
relate on the edition axis — the model that lets the living Novus Ordo calendar
coexist with the static traditional editions without either breaking the frozen
output contract. Reuses the corpus edition layout ([`corpus-schema.md`](corpus-schema.md)),
the particular-calendar overlay mechanics (Epic #75), and the rite-unit URNs
(comparison Epic #299)._

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

## Overlays are not editions

A **particular calendar** — SSPX, FSSP, a diocese — and a **conference propers**
set are **`overlay`-type layers** ([`platform-urn-scheme.md`](platform-urn-scheme.md)),
applied *atop* an edition or a snapshot. They add, remove, or re-rank observances;
they do **not** restate the rubric family. An overlay is therefore **never an
edition**: `roman:rubricae-1960` is an edition, `directorium:overlay:roman:sspx` is a
layer resolved over it. The same overlay engine that applies a diocesan calendar
applies a decree — they are the same mechanism at different cadences.

## The edition validity window

`edition.json` (per [`corpus-schema.md`](corpus-schema.md)) carries a **validity
window** and a **supersession** pointer:

- the **validity window** is the historical span over which the edition governed;
- **supersession** names the edition that replaced it.

Resolving a date **outside** an edition's window is anachronistic and is
**flagged** (consistent with [`KNOWN-LIMITATIONS.md`](../../KNOWN-LIMITATIONS.md)),
not silently answered — the resolver still returns a day, marked as resolved
outside the edition's window.

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
