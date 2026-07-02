# The precedence & concurrence model

How the temporal skeleton and the sanctoral overlay are composed into one
resolved liturgical day under the **1962 rubrics (Rubricae 1960 / editio typica
1962)**. Epic #29.

The temporal fillers and the sanctoral overlay each emit standalone offices
(both implement `Calendar\RealizedObservance`). The resolver decides, for a
date, which single office is **celebrated**, which are **commemorated**, which
are **displaced** (transferred or omitted), and reports the **tempora** — then
assembles the `LiturgicalDay` and backs the public `day()` entry point.

## The per-edition seam

All edition-specific precedence knowledge lives behind `Precedence\PrecedenceRules`
(a new `src/Precedence/` namespace) — the abstraction the rubric-family epic
(#59) will use to add the 1954, 1955, and Novus Ordo editions. The resolver
pipeline is edition-agnostic; only `Rubrics1962Precedence` exists now. **No
accessor is added to `RealizedObservance`**: the identity/attribute seam stays
edition-neutral, and 1962-specific facts (a day's season, the Triduum) are read
inside the rules object — season through a narrow `instanceof TemporalObservance`
check, the Triduum from the `PrecedenceContext` the resolver builds per day.

## The Table of Liturgical Days (#30)

1962 precedence is **not** a total order on `RankClass`: a first-class Sunday of
Lent and a first-class saint's feast are both class I yet resolve oppositely.
The true sort key is a `Precedence\PrecedenceTier` (ordinal, lower = higher;
plus a `subOrder` tiebreak). `Rubrics1962Precedence::tierOf()` maps an office to
its line in the **1960 Table of Liturgical Days (Codex Rubricarum n. 91)** — the
tier ordinals below are exactly n. 91's line numbers, so gaps are meaningful:
lines this edition's data cannot yet distinguish (proper vs universal-Church vs
indult feasts of one class — n. 91 lines 12/13, 19/20, 23) collapse onto the
universal-Church line and are refined when the corpus (#38) carries that
provenance.

| Line | Category | Detected from |
|------|----------|---------------|
| 1 | Christmas, Easter, Pentecost | identity (`christmas:nativity`, `paschal:easter`, `paschal:pentecost`) |
| 2 | the Sacred Triduum | `PrecedenceContext::isTriduum()` |
| 3 | Epiphany, Ascension, Trinity, Corpus Christi, Sacred Heart, Christ the King | identity |
| 4 | Immaculate Conception, Assumption | identity |
| 5 | Vigil (24 Dec) & Octave-day (1 Jan) of Christmas | identity |
| 6 | Sundays of Advent, Lent, Passiontide, Low Sunday | `kind=sunday, class I` |
| 7 | Ash Wednesday & Mon–Wed of Holy Week (privileged I ferias) | `kind=feria, class I` |
| 8 | All Souls | `kind=office-of-the-dead` |
| 9 | Vigil of Pentecost | identity |
| 10 | days within the Easter & Pentecost octaves | id `…:easter-octave`/`…:pentecost-octave` |
| 11 | other first-class feasts (universal/proper/indult) | `kind=feast, class I` |
| 14 | second-class feasts of the Lord | `kind=feast, class II`, Lord id-set |
| 15 | second-class Sundays | `kind=sunday, class II` |
| 16 | other second-class feasts | `kind=feast, class II` |
| 17 | days within the octave of Christmas | `kind∈{within-octave,octave-day}, class II` |
| 18 | greater Advent ferias & the Ember Days | `kind=feria, class II` |
| 21 | second-class vigils | `kind=vigil, class II` |
| 22 | ferias of Lent & Passiontide (except Ember) | `kind=feria, class III`, season not Advent |
| 24 | third-class feasts | `kind=feast, class III` |
| 25 | ferias of Advent to 16 Dec (except Ember) | `kind=feria, class III`, season Advent |
| 26 | third-class vigils | `kind=vigil, class III` |
| 27 | the Saturday Office of Our Lady | `kind=lady-on-saturday` |
| 28 | ferias of the fourth class & commemorations | class IV / `kind=commemoration-only` |

The occurrence-critical facts this order encodes: the **privileged first-class
ferias (7) outrank ordinary first-class feasts (11)** — why the Annunciation is
transferred out of Holy Week; a **first-class Sunday (6) outranks a first-class
saint feast (11)** — why St Joseph is transferred off a Lenten Sunday; a
**Lenten feria (22) outranks a third-class feast (24)** — protecting the Lenten
weekday office; and within second class, **feast of the Lord (14) > Sunday (15)
> saint feast (16) > Christmas-octave day (17)**.

Source: *The New Rubrics of the Roman Breviary and Missal* (1960) n. 91,
cross-checked against the SSPX "Classifications of Feasts" transcription.

## Occurrence outcomes (#31)

When two offices fall on one day, the higher (by tier) is celebrated;
`Rubrics1962Precedence::occurrenceOutcome()` decides the loser's fate as an
`OccurrenceOutcome` — **commemorate | transfer | omit**:

- **Transfer** — only first-class *feasts* (n. 95), plus All Souls (n. 96b). A
  displaced first-class feast keeps its identity so the transfer queue (#34) can
  re-place it; no lower feast is ever transferred.
- **Omit** — on a day that admits no commemoration at all (the Triduum n. 23,
  the days within the Easter and Pentecost octaves n. 66, the first-class vigils
  n. 30), or when the loser is an *ordinary* office on a first-class day (which
  admits only a *privileged* commemoration, n. 111a).
- **Commemorate** — otherwise. A **privileged commemoration** (n. 108: a Sunday,
  a first-class day, a day within the Christmas octave, a feria of
  Advent/Lent/Passiontide; September Ember days and the greater Litanies are
  added with the data that carries them) survives even on a first-class day; an
  ordinary office survives on a second- to fourth-class day.

This yields the headline resolutions: the Immaculate Conception is celebrated
with the Advent Sunday **commemorated**; St Joseph on a first-class Lenten Sunday
is **transferred**; a third-class saint on a first-class Sunday is **omitted**; a
first-class feast in the Easter octave is **transferred**, a lower one **omitted**.
The per-day commemoration *count* limit (n. 111b–d / 114) is applied by the
resolver (#36); this method gives each pair's intrinsic outcome.

Source: the New Rubrics of the Roman Breviary and Missal (1960) nn. 92–114
(occurrence n. 93, transference nn. 95–96, commemorations nn. 106–114), from the
Divinum Officium transcription, cross-checked against the SSPX summary.

## Still to come in this epic

- **Occurrence coverage (#32–#33)** — second/third/fourth-class pairings and the
  Sunday-vs-feast-of-the-Lord exclusions (nn. 15, 112) over this same method.
- **The transfer queue (#34)** — a displaced first-class feast is transferred to
  the next free day (the Annunciation has a fixed target, the Monday after Low
  Sunday); resolved by a deterministic whole-year forward sweep.
- **Concurrence (#35)** — First vs Second Vespers of adjacent days.
- **Commemoration limits (#36)** — applying `Calendar\CommemorationLimit` with
  the class-I "privileged only" restriction and the zero-commemoration days.
- **Assembly (#37)** — building the `LiturgicalDay` (its four role-arrays become
  `list<RoledObservance>`) and wiring `day()`.
