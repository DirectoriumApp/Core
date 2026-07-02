# missalemeum oracle fixture

A pinned snapshot of the [missalemeum](https://www.missalemeum.com) 1962 calendar,
used by the validation harness (#45) as an **independent oracle** to cross-check the
engine's day resolution. missalemeum
([github.com/mmolenda/missalemeum](https://github.com/mmolenda/missalemeum), MIT,
© 2022 Marcin Molenda) is a separate implementation of the same Rubricae 1960 /
1962 calendar, so agreement between the two is strong evidence of correctness, and
disagreement flags a bug in one of them.

Per the project's oracle-authority policy, **missalemeum is the primary authority
for the base 1962 edition**; SSPX particular-calendar differences belong to the
v0.2 overlay, and Divinum Officium is a further cross-check.

## What is stored

Only calendar **facts** (uncopyrightable), harvested from the public API
`GET /en/api/v5/calendar/{year}`. One NDJSON file per year, one day per line:

```json
{"date":"2024-01-01","title":"Octave Day of Christmas","rank":1,"colors":["w"],"commemorations":0}
```

| field | meaning |
|---|---|
| `date` | ISO date `YYYY-MM-DD` |
| `title` | the office's English title — for readable divergence reports; **not compared** (we resolve Latin identities, not English display strings) |
| `rank` | liturgical class, `1` (highest) … `4` |
| `colors` | colour codes: `w` white, `r` red, `g` green, `v` violet, `b` black, `p` rose |
| `commemorations` | count of commemorations on the day |

`provenance.json` records the source, licence, API version, harvest date, and range.

## Range

Committed range: **2024–2026** (three years — exercises the full temporal cycle, a
leap year, and Easter's movement). The divergences are systematic and repeat yearly,
so a small range is representative; widen it by re-harvesting.

## Refreshing

Maintainer-only, run offline — the validation test reads the committed files and
never touches the network:

```
node tools/oracles/harvest-missalemeum.mjs [firstYear] [lastYear] [harvestDate]
```

Re-harvesting the same range with the same date reproduces byte-identical files
(the upstream calendar for a past/fixed year is deterministic). Refresh deliberately,
review the diff, and update the known-differences baseline (#48) alongside.
