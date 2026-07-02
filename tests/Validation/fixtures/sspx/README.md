# SSPX 1962 ordo oracle fixture

A pinned snapshot of the [SSPX 1962 ordo](https://1962ordo.today), used by the
validation harness (#45) as a **second, independent oracle** to cross-check the
engine's day resolution. The Society of St Pius X (District of the USA) publishes its
operative Rubricae 1960 / 1962 ordo as a free public web app backed by a JSON endpoint,
so it is a separate witness to the same calendar.

## Role: a witness, not the base authority

Per the project's oracle-authority policy, **missalemeum is the primary authority for
the base 1962 edition** (see `../missalemeum/README.md`). SSPX keeps a **particular
calendar** — the universal 1962 base plus its own observances (St Pius X and the Seven
Sorrows elevated to first class, tagged `(FSSPX)` upstream) — so it is not an authority
on the base edition. Its two jobs here are:

1. **Corroboration.** Where SSPX and missalemeum agree on a class the engine does not,
   two independent sources indict the same base-1962 rank. Those rows also feed the
   accuracy worklist (#428).
2. **Overlay discovery.** Where SSPX alone differs, the difference is a candidate entry
   for the **v0.2 SSPX particular-calendar overlay** — the R2 priority.

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
(e.g. All Saints 2026, which omits its class token) are skipped. The engine ↔ SSPX
class mismatches are frozen, categorised, into `sspx-differences.ndjson`; the test
(`SspxOracleTest`) is green when the live mismatches equal that baseline exactly, so a
regression and a drift both fail until reviewed.

Because SSPX is a particular calendar, **every mismatch is expected** — the baseline is
a living catalogue of how the base engine relates to SSPX, not a bug list.

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
