# Temporal fill model — the Christmas cycle (Advent → Epiphany)

_Design record for Core #17. Status: accepted. Baseline edition: **Rubricae Generales 1960
(= 1962)**._

## What a temporal fill produces

The temporal cycle (Epic #14) is filled block by block. Each block-filler answers one question for
every day it owns: **which temporal office is celebrated, and how is it realized this year?** That
answer is a `TemporalObservance` — the pairing of

- a Layer-1 **identity** (`ObservanceId`, e.g. `roman:temporale:advent:sunday-1`) and its intrinsic
  `ObservanceKind`; with
- the Layer-2 **per-edition realization** the day carries in this edition: its `Season`, its
  `RankClass` (the 1960 I–IV class), and its `ElementColour`; plus
- a Latin display name (the invariant `la` label, mirroring `Observance`'s Latin-required rule).

`TemporalObservance` is deliberately **not** the sanctoral `Observance` shell. A temporal day has no
*titular* in the sanctoral sense — "Monday in the first week of Advent" is a weekday of a season, not
a feast of a subject — so forcing it through `Observance`'s non-empty `titulars` contract would be a
category error. Temporal identity is fully carried by the structural `ObservanceId`; the realization
travels beside it. Bridging temporal observances into `LiturgicalDay::tempora` (typed
`list<Observance>` today) is a later-epic concern (precedence #29 / output contract #52) and may add a
titular-free `Observance` factory or widen the aggregate; it is out of scope here.

## The Christmas cycle

`ChristmasCycle::forYear($year)` fills the **Advent-to-Epiphany block** — traditionally the *Christmas
cycle*, the counterpart of the Easter cycle (Septuagesima → Pentecost) built on the `PaschalSkeleton`.
`$year` is the civil year in which **Advent begins and Christmas falls**; Epiphany and the Sundays
after it fall in `$year + 1`. The block spans `[First Sunday of Advent, Septuagesima)` — Septuagesima
of the following year is the first day handed off to the Easter cycle (#18), so it is the exclusive
end boundary and is computed from `PaschalSkeleton::forYear($year + 1)`.

### First Sunday of Advent — the anchor

Advent is anchored to the **Sunday nearest the feast of St Andrew (30 November)**, equivalently the
**fourth Sunday before Christmas**. It is computed structurally, never from Easter:

```
fourthSunday = the latest Sunday on or before 24 December
firstSunday  = fourthSunday − 21 days
```

This lands the First Sunday in the window **27 November – 3 December** for every year. When 24 December
is itself a Sunday, the fourth Sunday of Advent *is* 24 December (a **short Advent**, which happens iff
Christmas falls on a Monday); the Vigil of the Nativity then supersedes it (below).

### Per-day classification

Each day is assigned exactly one **principal** temporal office by this precedence — *fixed temporal
feast › Sunday › day within an octave › feria*:

| Day | Identity | Kind | Season | Class | Colour |
|-----|----------|------|--------|:-----:|--------|
| 1st Sunday of Advent | `…advent:sunday-1` | Sunday | Advent | **I** | violet |
| 2nd–4th Sundays of Advent | `…advent:sunday-{2..4}` | Sunday | Advent | II | violet (3rd = violet, **rose permitted** — Gaudete) |
| Ferias of Advent (to 16 Dec) | `…advent:week-{w}:feria-{d}` | Feria | Advent | IV | violet |
| Greater ferias (17–23 Dec) | `…advent:week-{w}:feria-{d}` | Feria | Advent | II | violet |
| Advent Ember Wed/Fri/Sat | `…advent:quattuor-temporum:{d}` | Ember day | Advent | II | violet |
| Vigil of the Nativity (24 Dec) | `…christmas:vigil` | Vigil | Advent | **I** | violet |
| The Nativity (25 Dec) | `…christmas:nativity` | Feast | Christmastide | **I** | white |
| Days within the Octave (26–31 Dec) | `…christmas:within-octave:day-{2..7}` | Within octave | Christmastide | II | white |
| Sunday within the Octave | `…christmas:sunday-within-octave` | Sunday | Christmastide | II | white |
| Octave Day / Circumcision (1 Jan) | `…christmas:octave-day` | Octave day | Christmastide | **I** | white |
| Ferias after the Octave (2–5 Jan) | `…christmas:post-octavam:feria-{d}` | Feria | Christmastide | IV | white |
| Sunday after the Octave (2–5 Jan) | `…christmas:sunday-after-octave` | Sunday | Christmastide | II | white |
| The Epiphany (6 Jan) | `…epiphany:domini` | Feast | Epiphany | **I** | white |
| Ferias after Epiphany (before the 1st Sunday) | `…epiphany:post-epiphaniam:feria-{d}` | Feria | Epiphany | IV | green |
| Sundays after Epiphany | `…epiphany:sunday-{n}` | Sunday | Epiphany | II | green |
| Ferias after Epiphany (in week n) | `…epiphany:week-{n}:feria-{d}` | Feria | Epiphany | IV | green |

Ranks follow **Rubricae Generales 1960**: first-class Sundays are only the First Sunday of Advent (n.
11), so Advent II–IV and every Sunday after Epiphany are second class; second-class ferias are the
Advent ferias of 17–23 December and the Ember days (n. 23); all remaining ferias are fourth class.

Weekday tokens use the liturgical feria numbering (Monday = `feria-2` … Friday = `feria-6`, Saturday =
`sabbatum`); Sundays are addressed by their slot, never a feria token.

### Scope boundaries (deferred, by design — not silent gaps)

This filler is a **temporal skeleton**. Composing feasts and transfers on top of it is later work:

- **Special movable feasts that overlay this cycle** — the Most Holy Name of Jesus, the Holy Family
  (the Sunday within Jan 2–13), and the Commemoration of the Baptism of the Lord (13 Jan) — are **#22**.
  The skeleton emits the underlying temporal office (the Sunday after the Octave on 2–5 Jan; the 1st
  Sunday after Epiphany, green) on those days; the feast overlay lands later. Note the 2–5 Jan Sunday
  is named *Dominica post Octavam Nativitatis*, **not** the anachronistic "II Sunday after Christmas"
  (a 1969-calendar construct absent from the 1962 books).
- **The sanctoral** filling the Christmas octave (Ss. Stephen, John, the Holy Innocents, …) is **#23**;
  the skeleton emits the temporal "day within the Octave" beneath them.
- **Precedence and commemoration** when a temporal office concurs with a sanctoral or movable feast —
  including the Vigil-vs-4th-Sunday concurrence of a short Advent — is **#29**.
- **Edition sensitivity.** Ranks/colours here are 1960. The days after Epiphany are green because the
  octave of the Epiphany was suppressed in 1955; in the older editions (Divino Afflatu, Tridentine)
  6–12 January are white days within that octave. Those engines are the historical-edition epics.
