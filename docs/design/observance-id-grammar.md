# Observance identity & ID grammar

_Status: approved (issue #9). This is the durable design record for the engine's cornerstone type._

## The one idea: identity ≠ attributes

An observance's **identity** is separated from everything that varies about it. Three strictly-layered
concerns, joined by one immutable key:

| Layer | What it is | Varies by | Lives in |
|-------|-----------|-----------|----------|
| **1 · Identity** | the slug — names the subject/slot | nothing (stable across every year **and** every rubric edition) | `ObservanceId` / `Observance` |
| **2 · Edition attributes** | rank, colour, octave, placement, precedence | rubric edition (1570 → 1962 → Novus Ordo) | per-edition attribute records (#10–#13, edition epics) |
| **3 · Year realization** | resolved date, principal/commemorated/transferred | calendar year | resolver output |

The identity slug carries **no rank, colour, octave, or date**. This is the load-bearing exclusion: the
same saint keeps one identifier whether it is III class in 1962 or higher with an octave in 1954, and the
identifier is the join key for the per-edition attributes and the stable cross-system export (#55).

## Grammar

```
ObservanceId := <rite> ":" <cycle> ":" <body>

regex (Roman rite):
  ^roman:(temporale|sanctorale|votive):[a-z0-9]+(-[a-z0-9]+)*(:[a-z0-9]+(-[a-z0-9]+)*)*$
```

- **rite** — the leading segment; only `roman` today. It dispatches to that rite's cycle sub-grammar, so
  other rites are additive with **zero retrofit** of existing identifiers. See
  [`platform-urn-scheme.md`](platform-urn-scheme.md).
- **cycle** — `temporale` | `sanctorale` | `votive`.
- **body** — two sub-grammars keyed by cycle:
  - *temporale*: `<anchorFamily> ":" <slot> [":" <coordinate>]*`
  - *sanctorale* / *votive*: `[<provenance> ":"] <subject-slug>`

**Encoding.** Lowercase ASCII letters and digits; hyphen separates words *within* a segment; colon
separates segments. No spaces, underscores, uppercase, or diacritics — Latin is transliterated
(`æ → ae`, `ë → e`). Canonical form is produced by `toString()`; `parse(toString(x)) === x` for every
valid id, and `equals()` is pure string identity.

## Types

- **`Rite`** — `roman` (others reserved: ambrosian, mozarabic, byzantine, …). Each future rite has its
  own engine and cycle sub-grammar; the Roman engine is never generalized to fit them.
- **`Cycle`** — `temporale` (movable/seasonal backbone + fixed temporal points) · `sanctorale`
  (fixed-date saints/mysteries + special-movable feasts like Christ the King) · `votive` (offices that
  appear only on otherwise-free days, e.g. Our Lady on Saturday).
- **`AnchorFamily`** (temporale body head) — `paschal` (signed offset from Easter) · `advent` · `christmas`
  · `epiphany` · `civil-fixed` (a true absolute civil date) · `month-computed` (a computed civil-month
  landmark, e.g. the September Embers). Every family names a genuine computation, so it honestly routes
  the resolver's date arithmetic.
- **`ObservanceKind`** — the intrinsic species, stable across editions: `sunday`, `feria`, `feast`,
  `special-movable`, `vigil`, `octave-day`, `within-octave`, `ember-day`, `rogation-day`,
  `lady-on-saturday`, `commemoration-only`, `office-of-the-dead`.

## Temporal addressing — structural only (Route 1)

Each temporal day has **exactly one** identifier: its structural/named form
(`roman:temporale:paschal:ash-wednesday`, `roman:temporale:paschal:feria-5-week-2`). The Easter-offset
(`easter-offset:-46`) is **not** an identifier — it lives in the placement rule / resolver as the
arithmetic that computes the date. Rejecting a second spelling per day means `equals()` is always correct
with no alias registry and no drift. (Offset aliases could be added later, non-breaking, if a real need
appears.) The parser enforces this: for `temporale`, the anchor family must be valid and the reserved
token `easter-offset` may not appear.

## Stability model

- **One identifier across editions.** Adding the 1954/1955/Novus-Ordo engines adds per-edition attribute
  records, never new identifiers. A subject dropped in an edition produces *absence* (no record), never a
  variant id.
- **Double-identity vs distinct-in-slot.** A day may legitimately bear two liturgical identities (Low
  Sunday *is* the Octave Day of Easter; 1 January is the Octave of Christmas *and* the Circumcision):
  one canonical id + a `secondaryFacet` alias. This is different from a *distinct feast occupying a
  temporal slot* (Trinity Sunday, Christ the King) — those have their own id and occupy the slot via the
  occurrence engine; they are never aliased to it.
- **Names are not identity.** Display names (Latin required, plus vernaculars) are keyed *by* the id;
  editing a name never touches identity.

## Worked examples

| Observance | Identifier | Note |
|-----------|-----------|------|
| St Lawrence | `roman:sanctorale:laurentius` | III class in 1962, higher + octave in 1954 — one id, two attribute records |
| St Matthias | `roman:sanctorale:matthias` | Feb 24→25 leap-year shift is a placement attribute; id unchanged |
| John Baptist (two feasts) | `roman:sanctorale:ioannes-baptista-nativitas` / `…-decollatio` | two occasions, one person → two ids |
| Gaudete | `roman:temporale:advent:sunday-3` | `roseAllowed` is a colour attribute, not in the id |
| Ash Wednesday | `roman:temporale:paschal:ash-wednesday` | offset (`easter-offset:-46`) is resolver math, not an id |
| Christmas Vigil | `roman:temporale:christmas:vigil` | displaces the 4th Advent Sunday via ordinary precedence |
| Trinity Sunday | `roman:temporale:paschal:trinity` | its own id; *occupies* the 1st-Sunday-after-Pentecost slot |
| Low Sunday | `roman:temporale:paschal:low-sunday` | one canonical id + `secondaryFacet` = octave-day-of-easter |
| Our Lady on Saturday | `roman:votive:maria-in-sabbato` | `votive` cycle; appears only on free Saturdays |
| Christ the King | `roman:sanctorale:christus-rex` | special-movable, last Sunday of October |
| All Souls | `roman:sanctorale:omnium-fidelium-defunctorum` | office of the dead; black; displaces off a Sunday |

## What issue #9 builds

The **identity layer only**: `ObservanceId` (+ `Rite`, `Cycle`, `AnchorFamily`, `ObservanceKind`),
`IdentityAliases`, and the `Observance` shell (id, kind, titulars, names, aliases). The per-edition
attributes (rank #10, colour #11, season #12, and the rest) and the `LiturgicalDay` aggregate + `day()`
(#13) attach to this identity in their own issues.
