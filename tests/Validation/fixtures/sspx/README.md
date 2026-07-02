# SSPX 1962 ordo oracle fixture

A pinned snapshot of the [SSPX 1962 ordo](https://1962ordo.today), used by the
validation harness (#45) as a **second, independent oracle** to cross-check the
engine's day resolution. The Society of St Pius X (District of the USA) publishes its
operative Rubricae 1960 / 1962 ordo as a free public web app backed by a JSON endpoint,
so it is a separate witness to the same calendar.

## Role: the SSPX particular-calendar conformance gate

SSPX keeps a **particular calendar** — the universal 1962 base plus the Society's own
observances (St Pius X and the Seven Sorrows of Sep 15 elevated to first class, tagged
`(FSSPX)` upstream). As of #80 the engine resolves **under the SSPX overlay** (#76/#78)
and this fixture is its **authority**: the ordo is the source of truth for the SSPX
particular calendar. The harness's two jobs are:

1. **Conformance.** Every FSSPX-tagged particular (`particular:true`) the ordo publishes
   must match the engine under the overlay — the overlay reproduces the Society's proper
   calendar exactly. `SspxOracleTest::testOverlayModelsEverySspxParticular` fails if a
   new proper feast appears upstream or the overlay breaks.
2. **Tracked residual.** The differences that remain (all `particular:false`) are frozen
   in a categorised baseline: base-1962 ranks the *base* engine still gets wrong,
   corroborated by **missalemeum** (the primary base authority — see
   `../missalemeum/README.md`; the September Ember week #439, the Ascension vigil #440,
   n. 33 #441; also feeding #428), plus two SSPX divergences the fixed-date overlay does
   not model — the movable Seven Sorrows (Friday after Passion Sunday, Easter-relative)
   and the Vigil of the Assumption (third class upstream against a second-class vigil
   under the 1960 Code of Rubrics n. 91, treated as a feed artifact, not conformed to).

## What is stored

Only calendar **facts** (uncopyrightable), harvested from the public endpoint
`GET /get-liturgical-days/`. One NDJSON file per year, one day per line:

```json
{"date":"2024-09-03","name":"Saint Pius X","klasse":1,"particular":true,"feast":false}
```

| field | meaning |
|---|---|
| `date` | ISO date `YYYY-MM-DD` |
| `name` | the office's English name — for readable divergence reports; **not compared** |
| `klasse` | liturgical class `1` (highest) … `4`, parsed from the German localisation; `null` when absent upstream |
| `particular` | `true` when upstream tags the day `(FSSPX)` — an SSPX particular observance |
| `feast` | upstream feast flag |

`provenance.json` records the source, endpoint, harvest date, and range.

## What is compared

Only **class** is compared: the feed does not expose colour or a commemoration count,
so those are not checked (a documented allowance). Days whose class is absent upstream
(e.g. All Saints 2026, which omits its class token) are skipped. The
engine-under-overlay ↔ SSPX class mismatches are frozen, categorised, into
`sspx-differences.ndjson`; the test (`SspxOracleTest`) is green when the live mismatches
equal that baseline exactly, so a regression and a drift both fail until reviewed.

Because the overlay conforms on every FSSPX particular, the baseline holds **no**
`sspx-particular` rows — it is the tracked residual: base-1962 ranks the base engine
still gets wrong (a bug list, cross-linked to #428/#439/#440/#441) plus the two
documented `particular:false` divergences the fixed-date overlay does not model.

## Range and coverage

Committed range: **2024–2026**, the same three calendar years as the missalemeum
fixture, so the two oracles can be cross-checked day for day. The endpoint offers
2017–2026; widen by re-harvesting. The feed omits a small number of days
(e.g. 2024-06-24, 2025-04-28); those days are simply not compared.

## Refreshing

Maintainer-only, run offline — the validation test reads the committed files and never
touches the network:

```
node tools/oracles/harvest-sspx.mjs [firstYear] [lastYear] [harvestDate]
```

Re-harvesting the same range with the same date reproduces byte-identical files (past
years are fixed upstream). Refresh deliberately, review the diff, and update the
differences baseline (`php bin/freeze-sspx-baseline.php`) alongside.
