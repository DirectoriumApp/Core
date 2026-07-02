# The corpus schema & on-disk layout

The documented schema for the generated static **calendar corpus** — the CC0 data
the engine will load in place of the hand-written `SeedSanctoralData` and the
inline attribute literals. Epic #38, issue #39. This defines the shapes and the
layout; the Node generator (#40) validates facts against them and emits the
corpus, and the PHP loaders (#41–#43) read it.

## Principles

- **Identity / edition / year (the 3-layer model).** An **identity record**
  carries only edition-invariant identity — the `ObservanceId` slug, kind,
  titulars, names, aliases — and **never** rank, colour, octave, or date. Rank,
  colour, and placement are **per-edition attribute** records keyed by
  `(id, edition)`. Per-year realization (the resolved date, role) is computed by
  the engine and is never stored. See `docs/design/observance-id-grammar.md`.
- **Facts are data; computation is code.** The corpus holds values a source
  *asserts* (a month/day, a rank, a colour, a Latin title, an Easter offset, an
  n.91 line number). The algorithms that consume them (Computus, the temporal
  sweeps, the resolver, occurrence/concurrence) stay in PHP. See
  `docs/design/output-contract.md` for how a resolved day is then serialised.
- **Born cited.** Every asserted datum carries a provenance marker into the
  source registry; the generator refuses to emit an uncited datum (#44).
- **Reproducible & CC0.** The corpus is generated deterministically and committed;
  the dataset is dedicated to the public domain under CC0-1.0, separate from the
  AGPL-3.0 engine.

## On-disk layout

```
Core/
  tools/generator/facts/           # hand-authored, cited YAML input (#40/#41; the clean-room boundary)
  data/corpus/                     # GENERATED, COMMITTED, CC0 — read-only to the engine
    MANIFEST.json                  #   corpusVersion, build metadata, file inventory + sha256
    LICENSE.txt                    #   CC0-1.0 dedication for the dataset
    schema/                        #   JSON Schema (draft 2020-12) for every record shape
      source.schema.json
      identity.schema.json
      attributes.sanctorale.schema.json
      placement.sanctorale.schema.json
      attributes.temporale.schema.json
      temporal-skeleton.schema.json
      precedence-tier.schema.json
      precedence-rules.schema.json
    sources.ndjson                 #   source registry -> introibo:source:<key>
    identity/
      sanctorale.ndjson            #   Layer-1 identity (sanctoral): NO rank/colour/date
      temporale.ndjson             #   Layer-1 identity (temporal archetypes): structural only
    temporal/
      skeleton.ndjson              #   Easter offsets + block->season (structural)
    editions/
      roman-rubricae-1960/
        edition.json               #   edition metadata (id, validity window, label)
        attributes.sanctorale.ndjson   # Layer-2: rank, colour, optional title override (cited)
        placement.sanctorale.ndjson    # month/day/vigilOf (edition-varying placement, cited)
        attributes.temporale.ndjson    # Layer-2: rank, colour, latinName by archetype (cited)
        precedence.tiers.ndjson        # n.91 line->ordinal table
        precedence.rules.ndjson        # membership lists, commemoration limits, zero-commem set
```

An **edition** is a directory under `editions/`; adding the 1954, 1955, or Novus
Ordo edition is a new sibling directory that reuses the shared `identity/` and
`temporal/skeleton.ndjson` unchanged — the physical expression of the 3-layer
model.

## File formats

- **NDJSON** (newline-delimited JSON, one record per line, UTF-8 no BOM, LF
  endings, trailing newline) for every record-bearing file. Line-oriented so a
  wrong rank shows up as a one-line diff in a PR; PHP 7.4 reads it with
  `fopen`/`fgets`/`json_decode` at constant memory and **zero Composer deps**.
- **Pretty JSON** for singletons (`MANIFEST.json`, `edition.json`) and the
  `schema/*.schema.json` files.
- **YAML** for the hand-authored *facts* under `tools/generator/facts/` (input
  only — never shipped in the corpus; the generator emits NDJSON from it).

Within every record, keys are emitted in a fixed, recursively code-unit-sorted
order, so serialisation is byte-stable (the generator's job, #40).

## Provenance — the `cite` marker

Every **asserted datum** carries provenance. Two granularities:

- A **record-level** `cite` (a source key) when one source backs the whole record.
- A **field-level** `cites` map when different fields of a record come from
  different sources — `{ "&lt;field&gt;": "&lt;sourceKey&gt;" }`.

A `sourceKey` is the local key of a row in `sources.ndjson`; its stable URN is
`introibo:source:&lt;key&gt;` (the platform URN scheme). The generator fails closed
if any required datum lacks a citation or names a key absent from the registry
(#44). This satisfies #39's "each text string carries a CC0 provenance marker":
every shipped string is traceable to a source, and the dataset as a whole is CC0.

### `sources.ndjson` — a source registry row

```json
{"key":"mr-1962","urn":"introibo:source:mr-1962","kind":"text","title":"Missale Romanum, editio typica 1962","publisher":"Typis Polyglottis Vaticanis","year":1962,"rights":"reference","note":"Rank/colour facts; titles only where public-domain."}
```

`kind` ∈ `text` | `reference` | `oracle`. `rights` ∈ `public-domain` |
`reference` (consult-only; a title may not be transcribed from a `reference`
source) | `oracle` (validation only, #45).

## Record shapes

### Identity — `identity/sanctorale.ndjson`

Edition-invariant. **No rank, colour, or date.**

```json
{"id":"roman:sanctorale:ioseph","kind":"feast","titulars":["ioseph"],"names":{"la":"S. Ioseph Sponsi B.M.V. Confessoris"},"cites":{"names.la":"mr-1920"}}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | `ObservanceId` slug; the stable cross-system id. |
| `kind` | string | `ObservanceKind` (open enum). |
| `titulars` | string[] | ≥1 titular subject slug. |
| `names` | object | Locale → name; `la` required (the invariant fallback). |
| `aliases` | object \| absent | `IdentityAliases` (secondaryFacet/splitFrom/…); omitted when none. |
| `cites` | object | Field → sourceKey for each asserted string (at least `names.la`). |

### Identity — `identity/temporale.ndjson`

Temporal identity is **archetypal**, not per-day: the fillers mint concrete
slugs (`roman:temporale:advent:week-4:feria-2`) algorithmically, so the corpus
describes the *archetypes* they branch on, not thousands of days.

```json
{"archetype":"advent-greater-feria","kind":"feria","names":{"la":"Feria {ord} infra Hebdomadam {wk} Adventus"},"cites":{"names.la":"mr-1920"}}
```

| Field | Type | Notes |
| --- | --- | --- |
| `archetype` | string | Stable archetype key the temporal fillers reference. |
| `kind` | string | `ObservanceKind`. |
| `names.la` | string | Latin **template** (`{ord}`/`{wk}` placeholders the filler fills). |
| `cites` | object | Field → sourceKey. |

### Temporal skeleton — `temporal/skeleton.ndjson`

The structural facts the Paschal computation consumes — Easter offsets and the
block→season map. No rank/colour (those are the edition's `attributes.temporale`).

```json
{"slot":"septuagesima","offset":-63,"cite":"rg-1960"}
{"block":"holy-week","season":"passiontide","cite":"rg-1960"}
```

### Edition attributes — `editions/&lt;edition&gt;/attributes.sanctorale.ndjson`

Layer-2, keyed by `(id)` within the edition directory.

```json
{"id":"roman:sanctorale:ioseph","rank":1,"colour":{"base":"white"},"cites":{"rank":"rg-1960","colour":"rg-1960"}}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | Foreign key to an identity record. |
| `rank` | int | `RankClass` ordinal 1–4. |
| `colour` | object | `{ base, roseAllowed? }` — `base` a `Colour` value; `roseAllowed` optional. |
| `nameOverride` | object \| absent | Per-edition `{la:…}` when the edition reworded the title (Decision B). |
| `cites` | object | Per-field provenance. |

### Edition placement — `editions/&lt;edition&gt;/placement.sanctorale.ndjson`

Edition-varying fixed-date placement (a feast can move between editions).

```json
{"id":"roman:sanctorale:ioseph","month":3,"day":19,"cites":{"month":"rg-1960","day":"rg-1960"}}
{"id":"roman:sanctorale:assumptio-vigilia","month":8,"day":14,"vigilOf":"roman:sanctorale:assumptio","cites":{"month":"mr-1962","day":"mr-1962"}}
```

`vigilOf` (optional) is the id of the feast a vigil anticipates.

### Edition temporal attributes — `editions/&lt;edition&gt;/attributes.temporale.ndjson`

Rank/colour/name per **archetype** (Decision C), not per day.

```json
{"archetype":"advent-greater-feria","rank":2,"colour":{"base":"violet"},"cites":{"rank":"rg-1960","colour":"rg-1960"}}
{"archetype":"gaudete-sunday","rank":1,"colour":{"base":"violet","roseAllowed":true},"cites":{"rank":"rg-1960","colour":"rg-1960"}}
```

### Precedence tiers — `editions/&lt;edition&gt;/precedence.tiers.ndjson`

The n.91 Table of Liturgical Days as data: each row maps a tier selector to its
ordinal (= the n.91 line number) and sub-order.

```json
{"line":1,"ordinal":1,"subOrder":0,"selector":"greatest-doubles","cite":"rg-1960:n.91"}
```

### Precedence rules — `editions/&lt;edition&gt;/precedence.rules.ndjson`

The membership id-lists, commemoration limits by class, and the
zero-commemoration set the rules engine reads (replacing the private consts in
`Rubrics1962Precedence`).

```json
{"rule":"membership","name":"great-lord-feasts","ids":["roman:temporale:epiphany:domini","roman:paschal:ascension"],"cite":"rg-1960:n.91"}
{"rule":"commemoration-limit","dayClass":1,"limit":1,"cite":"rg-1960:n.107"}
```

### `MANIFEST.json`

```json
{"corpusVersion":"1962-2026-07-02.1","generatedFrom":"tools/generator","dataLicense":"CC0-1.0","files":[{"path":"identity/sanctorale.ndjson","sha256":"…","records":214}]}
```

`corpusVersion` is `1962-&lt;date&gt;.&lt;serial&gt;` (Decision D), sourced from an explicit
facts field — **never the wall clock** — so rebuilds are byte-identical. It flows
into `Provenance::corpusVersion()` in the output contract and carries no edition
token.

## Validation

Each shape has a JSON Schema (draft 2020-12) under `data/corpus/schema/`. The
Node generator validates every emitted record against its schema (#40); this
issue (#39) ships the schemas plus representative sample records and a test that
asserts the samples validate, so the contract is executable from the start.

## Licensing

The corpus is **CC0-1.0** (`data/corpus/LICENSE.txt` + the `dataLicense` field in
`MANIFEST.json`), which waives the EU *sui generis* database right over the
compilation — bare "public domain" would not. The repository is REUSE/SPDX
annotated so the CC0 dataset is machine-distinguishable from the AGPL-3.0 engine.
