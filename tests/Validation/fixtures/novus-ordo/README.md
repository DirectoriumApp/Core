# Novus Ordo (2002) oracle fixtures

Pinned, offline fixtures that cross-check our reformed General Roman Calendar
(`roman:novus-ordo-2002`) against **two independent, open-source calendar engines** — the
≥2-oracle standard for a new edition (roadmap v3, #260):

| Oracle | Engine | Licence | Shape |
| --- | --- | --- | --- |
| **LitCal** ([litcal.johnromanodorazio.com](https://litcal.johnromanodorazio.com)) | PHP | Apache-2.0 | whole-year; numeric `grade` 0…7 + colour |
| **calapi / In Adiutorium** ([calapi.inadiutorium.cz](http://calapi.inadiutorium.cz)) | Ruby | LGPL-3.0-or-later | per-day; `rank` string + colour, optional memorials listed separately |

Both are **run-and-compare oracles only, never a data source** (clean-room). They are
harvested offline by [`tools/oracles/harvest-novus-ordo.mjs`](../../../../tools/oracles/harvest-novus-ordo.mjs);
the validation test ([`NovusOrdoOracleTest`](../../NovusOrdoOracleTest.php)) reads the
committed files and never touches the network. See [`provenance.json`](provenance.json).

## What is compared

The three engines use different rank vocabularies, so — as with the missalemeum arm —
comparison is on robust, edition-neutral facts, normalised to a coarse grade they share:

```
solemnity  >  feast  >  memorial  >  feria
```

For every day, the principal office's **grade** is compared, and — on a matching,
non-ferial grade — its **colour** (colour is a membership test; `purple`→`violet`). Titles
are never compared. `known-differences.ndjson` freezes the residual as a categorised
baseline; the test is green when the live divergences equal it exactly (a regression and an
improvement both fail). Regenerate after a reviewed change with
`php bin/freeze-novus-ordo-baseline.php`.

Across the fixture years the engine agrees with **both** oracles on ~98% of day-by-day
comparisons. Every tracked difference below is explained, and on the contested days — where
the two oracles disagree with *each other* — our engine sits on the liturgically-correct
side.

## What this arm does and does not prove

It cross-checks the **grade and colour** of the day's principal office. It deliberately does
**not** compare the office's *identity* (the oracles use their own naming schemes; a title
crosswalk would re-encode the oracle rather than test against it). So a hypothetical
wrong-saint-of-equal-grade-and-colour swap is outside this arm's reach — identity is proved
elsewhere: the born-cited corpus provenance gate, the byte-frozen golden digest, and the
day-specific `NovusOrdoResolutionTest`. Feria-tier colour is compared only against calapi
(whose feria-day principal *is* the ferial); LitCal's feria-day principal is the day's
highest-grade event (an optional memorial), so its colour is not comparable there.

Two coverage notes for maintainers extending the fixtures:

- The **movable Immaculate Heart** (deferred, §2) happens in *both* 2025 and 2026 to fall on
  a Saturday already holding a *fixed* obligatory memorial, so it surfaces as
  `grade-core-higher` (the engine over-celebrates the fixed memorial). A year where that
  Saturday is otherwise free would instead show the engine's plain feria against the oracles'
  obligatory memorial — a new `grade-oracle-higher` row to baseline.
- Post-2002 decrees (§1) are now applied by the dated decree overlays (#366): the engine
  replays each decree from its effective date, so a later fixture year adds only whichever
  *further* universal memorials the reform decrees after those already authored, each a fresh
  tracked row until its decree file lands.

## The tracked differences

### 1 — Post-2002 decrees, now applied via dated decree overlays (#366)

The 2002 *editio typica* is a frozen snapshot; the living calendar is that snapshot plus
**dated decrees** the engine replays by their effective date (docs/design/edition-governance.md).
#366 landed the first two real post-2002 universal decrees, so the engine now resolves them —
retiring the rows the 2002-only baseline used to carry:

- **Mary, Mother of the Church** — Monday after Pentecost, decree *Ecclesia Mater* (11 Feb
  2018). The engine now celebrates the obligatory memorial (white); **both oracles agree**, so
  `2025-06-09` and `2026-05-25` are no longer tracked differences.
- **Mary Magdalene raised to a Feast** — decree *Apostolorum Apostola* (3 June 2016). The
  engine now reads a **feast** from 2016 onward, matching **calapi**. The committed **LitCal
  fixture** was harvested without this change (it grades her an obligatory memorial), so the
  engine now correctly reads *higher* than that fixture — the one place edition-governance
  makes Core lead an oracle whose snapshot lags the decree:
  - `2025-07-22`, `2026-07-22` (LitCal) — `grade-core-higher`. Core follows the decree and
    calapi corroborates; a LitCal re-harvest reflecting the 2016 decree would retire both rows.

### 2 — The movable Immaculate Heart of Mary (deferred; KNOWN-LIMITATIONS)

The Immaculate Heart is a movable memorial (Saturday after the Sacred Heart) we do not yet
compute. In these years it collides with a *fixed* obligatory memorial; two memorials on one
day both become optional, so the oracles resolve the **feria** (with both as options), while
we — seeing only the fixed memorial — celebrate it:

- `2025-06-28` (vs St Irenaeus), `2026-06-13` (vs St Anthony of Padua) — both oracles.

### 3 — All Souls' *sui generis* grade

Both oracles grade the Commemoration of All the Faithful Departed a *solemnity*; we encode
RankClass II. We still place All Souls correctly — it displaces the occurring Sunday
(2 Nov 2025 is a Sunday) — so only the coarse grade *label* differs, not the winner.

- `2025-11-02`, `2026-11-02` — both oracles.

### 4 — Triduum colour convention (calapi)

calapi colours these days by the penitential season; we (and LitCal) colour them by the
day's Mass:

- **Holy Thursday** (`2025-04-17`, `2026-04-02`) — white (Mass of the Lord's Supper).
- **Holy Saturday** (`2025-04-19`, `2026-04-04`) — white (the Easter Vigil).

LitCal agrees with us (white) on both; calapi reads violet.

### 5 — Oracle colour quirks where our engine is correct

- **St Martha** (`2025-07-29`, `2026-07-29`, calapi red) — a holy woman: **white**.
- **St Martin of Tours** (`2025-11-11`, `2026-11-11`, calapi red) — a confessor-bishop:
  **white**.
- **Korean Martyrs** (`2025-09-20`, LitCal white) — martyrs: **red**; calapi corroborates
  our red. (Absent in 2026: 20 Sep is a Sunday.)

### 6 — Oracle-vs-oracle precedence disagreement (our engine matches the correct one)

- **Dedication of the Lateran on a Sunday** (`2025-11-09`, calapi green) — a Feast of the
  Lord outranks a Sunday in Ordinary Time (Table of Liturgical Days, line 5 over 6), so the
  day is the Dedication (white). We and LitCal resolve the Dedication; calapi kept the 32nd
  Sunday. (Absent in 2026: 9 Nov is a weekday, no conflict.)

### 7 — Lenten memorial presentation (LitCal)

- **Ss. Perpetua & Felicity** (`2025-03-07`) — in Lent an obligatory memorial is reduced to
  a commemoration; the day is the Lenten feria. calapi agrees with us (ferial principal, the
  saints commemorated); LitCal surfaces the memorial as the day's event.
