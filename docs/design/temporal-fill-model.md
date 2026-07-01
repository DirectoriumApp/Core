# Temporal fill model — the Christmas cycle, the Lenten cycle, and Holy Week

_Design record for Core #17 (Advent → Epiphany), #18 (Septuagesima → Passiontide), and #19 (Holy
Week & the Paschal Triduum). Status: accepted. Baseline edition: **Rubricae Generales 1960 (= 1962)**._

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

## The Lenten cycle

`LentenCycle::forYear($year)` fills the **Septuagesima-to-Passiontide block** — the penitential run
toward Easter, entirely Easter-anchored via the `PaschalSkeleton`. `$year` is the civil year in which
Easter falls (the whole block lies within it). The block spans `[Septuagesima, Palm Sunday)` — a fixed
56-day run (Easter−63 to Easter−7); Septuagesima is where the `ChristmasCycle` hands off, and Palm
Sunday is the exclusive end boundary handed to the Holy Week block (#19). Everything is **violet**.

Days are addressed by Easter offset (all identities sit under the `paschal` anchor family):

| Day | Identity | Kind | Season | Class | Colour |
|-----|----------|------|--------|:-----:|--------|
| Septuagesima / Sexagesima / Quinquagesima | `…paschal:{gesima}` | Sunday | Septuagesima | II | violet |
| Pre-Lenten ferias | `…paschal:{gesima}:feria-{d}` | Feria | Septuagesima | IV | violet |
| Ash Wednesday (Easter−46) | `…paschal:ash-wednesday` | Feria | Lent | **I** | violet |
| Ferias after Ash Wednesday (Thu–Sat) | `…paschal:post-cineres:feria-{d}` | Feria | Lent | III | violet |
| Sundays I–IV of Lent | `…paschal:lent-{1..4}` | Sunday | Lent | **I** | violet (IV = **rose permitted** — Laetare) |
| Lenten ferias (weeks I–IV) | `…paschal:lent-week-{w}:feria-{d}` | Feria | Lent | III | violet |
| Lenten Ember Wed/Fri/Sat (week I) | `…paschal:quattuor-temporum-quadragesimae:{d}` | Ember day | Lent | II | violet |
| Passion Sunday (Easter−14) | `…paschal:passion-sunday` | Sunday | Passiontide | **I** | violet |
| Ferias of Passion Week | `…paschal:passion-week:feria-{d}` | Feria | Passiontide | III | violet |

The raised Lenten ferial ranks are the point of the block (Rubricae Generales 1960, n. 23): Ash
Wednesday is a **first-class** feria; the Ember Days of Lent are **second class**; the other ferias of
Lent and Passiontide are **third class** — whereas the pre-Lenten ferias stay **fourth class**. The
Sundays of Lent are all **first class** (feasts yield to them), unlike the second-class pre-Lenten
Sundays and Advent II–IV.

Scope boundaries as above: the sanctoral (#23) and precedence/commemoration (#29) compose on top. In
Lent this notably includes transferring a first-class feast off a Sunday of Lent (St Joseph, the
Annunciation) and the movable Seven Sorrows on the Friday of Passion Week (#22/#23) — the skeleton
emits the underlying temporal feria there.

## Holy Week and the Paschal Triduum

`HolyWeek::forYear($year)` fills the summit of the year — the seven days `[Palm Sunday, Easter)`
(Easter−7 … Easter−1), Easter-anchored via the `PaschalSkeleton`. `$year` is the year Easter falls.
`LentenCycle` hands off at Palm Sunday; Easter Sunday opens Eastertide (#20). **Every day is first
class and Passiontide** — the guarantee that no sanctoral feast may displace it.

| Day | Identity | Kind | Class | Colour |
|-----|----------|------|:-----:|--------|
| Palm Sunday (Easter−7) | `…paschal:palm-sunday` | Sunday | **I** | violet |
| Monday–Wednesday of Holy Week | `…paschal:holy-week:feria-{2..4}` | Feria | **I** | violet |
| Maundy Thursday (Easter−3) | `…paschal:maundy-thursday` | Feria | **I** | white |
| Good Friday (Easter−2) | `…paschal:good-friday` | Feria | **I** | **black** |
| Holy Saturday (Easter−1) | `…paschal:holy-saturday` | Feria | **I** | violet |

The last three days are the **Sacrum Triduum**; `HolyWeek::triduum()` / `isTriduum()` mark them so the
precedence engine (#29) can give them absolute priority (they sit at the apex of the Table of
Precedence and admit no occurrence).

Colours are the **principal** vestment colour of each day's chief act under the 1955/1962 reform.
Good Friday is **black** (turning violet only for Communion) — the red of the modern rite is *not* the
1962 colour, so the issue's "red Good Friday" example is followed in spirit (a colour transition) but
corrected in fact. The within-day changes the reform introduced are a per-element concern of the
rubrics layer, not this skeleton: the **red** Palm-Sunday procession (violet Mass), the **violet**
Good-Friday Communion (black liturgy), and the **violet→white** Paschal Vigil (violet Holy Saturday).

## Shared helpers

The block fillers share `TemporalCalendar`, a stateless helper holding the plain UTC-midnight date
arithmetic (`addDays`, `daysBetween`, `sameDay`, `isSunday`, `utcDate`) and the one definition of
liturgical weekday naming (`roman`, `feriaToken`, `feriaLatin` — Monday = feria II … Saturday =
Sabbatum). Keeping the feria numbering in a single place is what lets every block name its days
identically; the remaining block fillers (#20–#22) build on the same helper.
