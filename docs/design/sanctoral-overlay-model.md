# The sanctoral overlay model

How the fixed-date sanctoral (the Proper of Saints) is placed onto the temporal
skeleton under the **1962 rubrics (Rubricae 1960 / editio typica 1962)**.

This is the companion to `temporal-fill-model.md`. The temporal fillers answer
"which office does the season give this day?"; the sanctoral overlay answers
"which fixed-date saint or feast also falls here, realized how?". Composing the
two into a single celebrated office — by precedence, commemoration, and transfer
— is the resolver's work (Epic #29); this overlay only **places** and (from #25)
**orders** the candidates. Epic #23.

## The realized-observance seam

The temporal and sanctoral layers must produce something the resolver can
compare uniformly and the output contract can serialize. That shared shape is
`Calendar\RealizedObservance` — an interface exposing the Layer-1 identity plus
the Layer-2 per-edition attributes a resolved day needs:

- `id(): ObservanceId`, `kind(): ObservanceKind`, `rank(): RankClass`,
  `colour(): ElementColour`, `latinName(): string`.

Two implementations:

- **`Temporal\TemporalObservance`** already had all five accessors, so it simply
  `implements RealizedObservance` — a behavioural no-op, zero churn to Epic #14.
  Its `season()` stays a temporal-only extra.
- **`Sanctoral\SanctoralObservance`** = an `Observance` identity shell (which,
  unlike a temporal day, carries titular subjects and names) + a `RankClass` +
  an `ElementColour`, delegating id/kind/latinName to the shell.

**Season is a property of the day, not of an office**, so it is deliberately
absent from the interface — a sanctoral feast is never made to carry a foreign
season. The interface lives in `Calendar\` (the consumer), keeping the identity
namespace (`Observance\`) free of any dependency on the attribute namespace.

`LiturgicalDay` will hold `RealizedObservance`s once the resolver assembles days
(#37); #23 delivers the vocabulary and both implementations, not the wiring.

## The sanctoral layer (`src/Sanctoral/`)

Mirrors the temporal block-fillers — a standalone layer keyed by civil date:

- **`SanctoralEntry`** — one fixed-date corpus datum: civil `month`/`day`, the
  `Observance` identity, its `RankClass` and `ElementColour`, and (for vigils,
  #27) the `vigilOfId` of the feast it precedes. Its shape is fixed now (the
  vigil field is a trailing optional) so later issues add *data*, not
  constructor churn.
- **`SanctoralData`** — the source interface (`entries(): list<SanctoralEntry>`).
  The loader depends on this, never on a concrete source.
- **`SeedSanctoralData`** — a provisional, representative, cited seed (below).
- **`SanctoralCalendar`** — the loader: `forYear($year, ?SanctoralData)` realizes
  every entry onto its civil date, producing `Y-m-d => list<SanctoralObservance>`
  via `on(date)` / `all()`.

### The corpus seam (#38)

`SeedSanctoralData` is **not** the calendar — it is a small slice authored in
code. The cited corpus generator (Epic #38, Wave 2) will provide a fuller
`SanctoralData` implementation and this seed is retired, with **no change to
`SanctoralCalendar`**. Entries are structured so a citation can attach later
without changing their shape (the accuracy-first "born cited" principle).

## The seed (representative, not complete)

Chosen to exercise every overlay mechanism while staying 100% correct for what
it includes. Ranks/colours verified against the 1960/1962 General Calendar.

| Date | Slug (`roman:sanctorale:…`) | Class | Colour | Notes |
|------|------|------|------|------|
| 02-22 | `cathedra-petri` | II | white | *Cathedra S. Petri Apostoli* (1960 merged the Jan 18 chair here; no "Antioch") |
| 02-24 | `matthias` | II | red | Apostle; bissextile → 02-25 in a leap year (#333) |
| 02-27 | `gabriel-a-virgine-perdolente` | III | white | bissextile → 02-28 in a leap year (#333) |
| 03-07 | `thomas-aquinas` | III | white | a real III-class feast |
| 03-19 | `ioseph` | I | white | transfer edge case → #29/#34 |
| 03-25 | `annuntiatio` | I | white | can fall in Holy Week / Easter octave → transferred (#34) |
| 06-24 | `nativitas-ioannis-baptistae` | I | white | |
| 06-29 | `petrus-paulus` | I | red | apostle-martyrs |
| 07-10 | `septem-fratres` | III | red | Seven Holy Brothers (+ Rufina & Secunda) — the #25 same-date fixture |
| 08-15 | `assumptio` | I | white | |
| 11-01 | `omnes-sancti` | I | white | |
| 11-08 | `quatuor-coronati` | IV | red | a **commemoration** (`kind = commemoration-only`): 1962 has no IV-class saints' *feast* — IV models the commemoration tier |
| 12-08 | `immaculata-conceptio` | I | white | **outranks** the II-class Advent Sunday (feast wins; Sunday commemorated) — #29 |
| 12-26 | `stephanus` | II | red | Christmas octave |
| 12-27 | `ioannes-evangelista` | II | white | Christmas octave |
| 12-28 | `innocentes` | II | red | Christmas octave — red under the 1962 books (1960 moved the red Mass onto the day) |

St Lawrence's feast (08-10) and the four surviving vigils are listed under
**Vigils (#27)** below; the bissextile shift is added in #333.

## What #24 establishes

The loader + the seam: places each seed entry on its civil date, guards the one
date that need not exist (a feast fixed to **29 February** is placed only in leap
years), and exposes every placed office through `RealizedObservance`. The
same-date **ordering** (#25), the **commemoration/displaced** containers (#26),
**vigils** (#27), **octave** interaction (#28), and the **bissextile** shift
(#333) build on this.

## Ordering co-occurring offices (#25)

When more than one fixed-date office lands on a civil date, `SanctoralCalendar`
orders them **highest rank first** (ascending `RankClass` ordinal, since class I
is ordinal 1), tie-broken by the canonical `ObservanceId` string — a fixed,
edition-invariant key, so the order is deterministic and reproducible run to run
(the validation oracle depends on it). This is a **pre-sort of candidates**;
deciding which office is actually celebrated, and which are commemorated or
displaced, is the resolver's work (#29).

## Roles and commemoration limits (#26)

Once resolution runs, each office plays a role on the day. `Calendar\CelebrationRole`
names the four (celebration | commemoration | displaced | tempora), and
`Calendar\RoledObservance` pairs a realized office with its role — so a displaced
office keeps its full data for the transfer queue (#34) and a commemoration keeps
everything needed to render it. `Calendar\CommemorationLimit` makes the 1960
counts representable (class I: 1, privileged only; II: 1; III/IV: 2; some days 0).

#26 only makes roles and limits **representable**. Assigning a role to each
office and enforcing the limits (including the class-I "privileged only"
restriction and the zero-commemoration days) is the resolver's work (#29 / #36).

## Vigils (#27)

Four sanctoral vigils survive the 1960 reform. Each is kept on the day before its
feast, is violet, and links to its feast by `vigilOfId` (carried through to the
realized `SanctoralObservance`, so the resolver can handle the vigil boundary):

| Vigil (`roman:sanctorale:…:vigilia`) | Date | Of feast | Class |
|------|------|------|------|
| `ioannes-baptista:vigilia` | 06-23 | Nativity of St John Baptist (06-24) | II |
| `petrus-paulus:vigilia` | 06-28 | Ss Peter & Paul (06-29) | II |
| `laurentius:vigilia` | 08-09 | St Lawrence (08-10) | III |
| `assumptio:vigilia` | 08-14 | Assumption (08-15) | II |

Rank is **per-vigil** — St Lawrence's is the one III-class vigil, the rest are
II. St Lawrence's feast (08-10, II, red) is seeded alongside its vigil. Every
other historical vigil was suppressed under the 1960 rubrics and is simply
absent from the data, so it is never emitted.

## Octaves (#28)

Under the 1960 rubrics only three octaves survive — **Christmas, Easter, and
Pentecost** — and all three are **temporal**: the block-fillers already emit
their days (kind `octave-day` / `within-octave`). Every other historical octave
(Epiphany, Corpus Christi, the Assumption, All Saints, the patronal octaves, …)
was abolished. So the sanctoral overlay generates **no** octaves at all — there
are no octave entries in the data and the loader invents none.

Where a sanctoral feast falls within the surviving Christmas octave — St Stephen
(26 Dec), St John (27 Dec), the Holy Innocents (28 Dec) — the overlay and the
temporal layer both produce an office for the day. Resolving that (celebrate the
feast, commemorate the octave) and transferring or omitting feasts impeded by
the Easter and Pentecost octaves is the resolver's work (#29). #28 only
establishes that the overlay adds no octaves and that the co-occurrence is
available to resolve.

## Scope boundaries (deferred, on purpose)

- **Precedence, commemoration, transfer** — Epic #29. The overlay never decides
  which office wins; `CelebrationRole` and the 1960 commemoration-count limits
  (I: 1 privileged, II: 1, III/IV: 2, some days 0) live there.
- **Octaves** — under 1960 all sanctoral octaves are abolished; only Christmas,
  Easter, and Pentecost survive and those are **temporal** (already emitted by
  the fillers). The overlay generates no octaves (#28).
- **The complete cited General Calendar** — Epic #38.
