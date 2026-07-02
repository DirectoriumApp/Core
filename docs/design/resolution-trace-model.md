# Resolution trace model (#233)

> How the engine shows its work. For any day, the trace explains **why** the office
> was chosen, what happened to every office it beat, and — with #235 — how the colour
> and season were derived, each step citing the governing rubric.

## Why

The engine's single highest-authority feature is being able to *justify* a resolution,
not merely assert it. A parish priest, a scholar, or a downstream app should be able to
ask "why is today violet, and why did St X yield to the feria?" and get a cited answer
drawn from the same decision the engine actually made — never a plausible-sounding
reconstruction. The trace is what powers the API's `?explain` surface and the Site's
"why this day?" panel.

## Where it attaches

The output contract (#52) reserves a day-level **`resolution`** slot for exactly this.
The trace fills it. It is not a new contract surface — the slot has shipped as `null`
since 1.0, and filling it is an additive (patch) change.

## Opt-in, so the default stays lean and stable

The trace is **not** emitted by default:

- `contract($date)` and `day($date)` are unchanged — `resolution` stays `null`, payloads
  stay small, and the golden-fixture digest (which hashes the default contract array)
  never moves. No re-freeze.
- `explain($date)` and `contract($date, true)` opt in: they resolve the year with a
  **tracing resolver** and populate `resolution`.

A tracing resolver is a distinct instance from the default one, memoised separately, so
turning tracing on cannot perturb the hot path a single byte.

## No decision/explanation drift

The cardinal risk of a "show your work" feature is that the explanation and the decision
fall out of sync — the engine does one thing and reports another. Two safeguards:

1. **Single source.** The reason for an occurrence outcome is produced by the *same*
   private method that decides it (`decideOccurrence()` returns the outcome **and** its
   reason together); `occurrenceOutcome()` and `explainOccurrence()` are thin readers of
   that one decision.
2. **The trace records the real field.** It is built inside the resolver's `assemble()`
   pass from the *actual* sorted candidates and the *actual* outcomes — not reconstructed
   afterwards from the resolved day — so contested cases (transfers, displaced vigils,
   two same-class feasts) are reported exactly as they were decided. The golden trace
   fixtures (#240) pin this, and a self-check asserts every reason's implied outcome
   equals the outcome actually recorded.

## The shape

```jsonc
"resolution": {
  "winner": {
    "id": "roman:temporale:advent:sunday:1",
    "line": 6,                               // its line in the Table of Liturgical Days
    "rule": "n91-table-of-liturgical-days",
    "summary": "celebrated as the highest office in the Table of Liturgical Days (line 6, first-class Sunday)",
    "citation": "rg-1960:91"
  },
  "candidates": [                            // the whole field of play, in precedence order
    { "id": "roman:temporale:advent:sunday:1", "rank": 1, "kind": "sunday",
      "tier": { "ordinal": 6, "line": 6, "selector": "first-sunday" } },
    { "id": "roman:sanctorale:…",             "rank": 3, "kind": "feast",
      "tier": { "ordinal": 24, "line": 24, "selector": "third-feast" } }
  ],
  "losers": [                                // every office the winner beat, and its fate
    { "id": "roman:sanctorale:…", "outcome": "commemorate",
      "rule": "commemoration-admitted",
      "summary": "commemorated: a third-class feast impeded by a higher office keeps a commemoration",
      "citation": "rg-1960:112" }
  ],
  "commemorationLimit": {
    "value": 1,
    "rule": "n111-commemoration-limit",
    "summary": "a first-class day admits one privileged commemoration",
    "citation": "rg-1960:111"
  },
  "colour": {                                // #235
    "base": "violet", "roseAllowed": false,
    "rule": "colour-of-celebration",
    "summary": "violet: the colour of the celebrated office (a first-class Sunday of Advent)",
    "citation": "rg-1960:117"
  },
  "season": {                                // #235
    "value": "advent",
    "rule": "season-of-temporal-office",
    "summary": "Advent: the season of the day's temporal office",
    "citation": "rg-1960:74"
  }
}
```

Every reason is a **`ResolutionReason`**: a stable machine `rule` key, a human `summary`,
and a `citation` reference (`key` or `key:locator`) that foreign-keys into
`sources.ndjson` — the same citation model as the corpus (#44). The `citation` may be
`null` only where no single rubric governs (kept honest rather than invented).

## Reason keys (1962)

The occurrence-outcome reasons reuse the exact Codex Rubricarum paragraphs already vetted
in `Rubrics1962Precedence`:

| rule | outcome | citation | n. |
|---|---|---|---|
| `n95-first-class-transfer` | transfer | `rg-1960:95` | only first-class feasts transfer |
| `n96b-all-souls-transfer` | transfer | `rg-1960:96` | All Souls reassigned when impeded |
| `n23-triduum-no-commemoration` | omit | `rg-1960:23` | the Triduum admits no commemoration |
| `paschal-octave-no-commemoration` | omit | `rg-1960:66` | the privileged octaves admit none |
| `n112-lord-sunday-exclusion` | omit | `rg-1960:15` | a feast of the Lord and a Sunday do not commemorate each other |
| `n108-ordinary-feria-no-commemoration` | omit | `rg-1960:108` | an ordinary feria yields without commemoration |
| `n111-first-class-privileged-only` | omit / commemorate | `rg-1960:111` | a first-class day admits only a privileged commemoration |
| `commemoration-admitted` | commemorate | `rg-1960:112` | otherwise the loser is commemorated |

The winner's reason cites the Table of Liturgical Days itself (`rg-1960:91`); the
commemoration limit cites `rg-1960:111`. Colour and season (#235) cite the general
rubrics on colour and the liturgical year.

## Delivery

- **#234** — the infrastructure and the office resolution (winner, candidates, losers,
  commemoration limit).
- **#235** — colour and season derivation added to the trace.
- **#236** — every step's citation resolvable into `sources.ndjson`, asserted.
- **#240** — golden-master trace fixtures for contested days, regression-tested against
  silent precedence drift.
