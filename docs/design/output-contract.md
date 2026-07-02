# The output contract

The versioned, serialisable shape that `Introibo\Core\contract()` emits — the
**public contract** the Api, Site, and Ordo repos build on. Epic #52.

The engine resolves a civil date to a `Calendar\LiturgicalDay` (Epic #29). That
aggregate is kept pure: it holds value objects and knows nothing about JSON. A
separate serialiser, `Contract\DayContract`, turns it into a stable, JSON-ready
structure. The shape is frozen at **contract version 1.0.0** and only ever grows
additively (reserved slots fill; keys are added, never removed or repurposed).

> This document describes the shape as built in #53. The full versioning
> bump-rules, the reserved-slot catalogue, and additional worked examples are
> expanded in #58.

## Where it lives

- **`Contract\DayContract`** — `from(LiturgicalDay, Provenance): self`, then
  `toArray(): array` (JSON-ready) or `toJson(): string`. `const SHAPE_VERSION`
  is the contract shape's SemVer.
- **`Contract\Provenance`** — the three provenance axes (below).
- **`Introibo\Core\contract(DateTimeImmutable): array`** — the public entry
  point, beside `day()`. It resolves and memoises the civil year once (shared
  with `day()`), then serialises. `day()` remains the value-object entry point,
  untouched.
- **`json_encode` flags (frozen):** `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  | JSON_THROW_ON_ERROR` — Latin names and ids/dates stay legible, and a failure
  is an exception, never a silent `false`.

## The three version axes

Every day carries three independent version stamps, so a consumer keys a cache on
all three — any one moving means the resolved output may differ:

| Field | Source | Meaning |
| --- | --- | --- |
| `contractVersion` | `DayContract::SHAPE_VERSION` | SemVer of the **shape** (1.0.0). |
| `corpusVersion` | `SanctoralData::version()` (seed: `1962-seed-<date>`) | The **calendar data** build; carries no edition token. |
| `engineVersion` | `Introibo::VERSION` | The **resolver** version; hand-bumped when output changes. |

The **edition** (`roman:rubricae-1960`) is the rules-family that governed the
resolution, and **rite** (`roman`) is its leading segment. The same 1962 corpus
can be resolved under different editions, which is why corpus and edition are
separate.

## The day shape

| Field | Type | Notes |
| --- | --- | --- |
| `contractVersion` | string | `1.0.0`. |
| `corpusVersion` | string | e.g. `1962-seed`. |
| `engineVersion` | string | e.g. `0.4.0`. |
| `rite` | string | `roman`. |
| `edition` | string | `roman:rubricae-1960`. |
| `date` | string | ISO-8601 `Y-m-d`. |
| `season` | string \| null | The day's tempus, from its temporal office. |
| `commemorationLimit` | int | Commemorations admitted by the day's class (I/II: 1, III/IV: 2). |
| `celebration` | office[] | The office celebrated (normally one). |
| `commemoration` | office[] | Offices commemorated within it. |
| `displaced` | office[] | Offices impeded this day (transferred or omitted). |
| `tempora` | office[] | The temporal office of the season, always reported. |
| `secondVespers` | object \| null | The evening concurrence with the next day. |
| `firstVespers` | null | Reserved (Office layer). |
| `resolution` | null | Reserved (the "why-this-won" trace). |
| `fasting` | null | Reserved (fasting/abstinence layer). |
| `calendar` | null | Reserved (calendrical/astronomical block). |

The **secondVespers** object is `{ outcome, favoursFollowing, holder, commemorated }`
— `outcome` is a `ConcurrenceOutcome` value; `holder`/`commemorated` are reserved
for the Office layer (null in 1.0).

## The office shape

Each office is self-describing: identity, per-edition attributes, and how it
fared this day.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | `ObservanceId` — the **stable** cross-system id. |
| `urn` | string | `introibo:observance:<id>`. |
| `role` | string | `celebration` \| `commemoration` \| `displaced` \| `tempora`. |
| `kind` | string | `ObservanceKind` (feast, feria, sunday, vigil, …). |
| `rank` | string | `I`–`IV`. |
| `rankOrdinal` | int | 1 (highest) – 4. |
| `season` | string \| null | Set on temporal offices; null on sanctoral. |
| `colour` | object | `{ base, roseAllowed }` — colour is per element; rose is a permission, not a base. |
| `names` | object | Locale → name; `la` always present, no baked vernacular in 1.0. |
| `titulars` | string[] | Titular subjects (sanctoral); `[]` for temporal. |
| `outcome` | string \| null | `commemorate` \| `transfer` \| `omit`; null on a celebration or the tempora. |
| `transferredTo` | string \| null | ISO date a displaced (impeded) office moved to. |
| `transferredFrom` | string \| null | ISO date a landed office was transferred from. |
| `vigilOf` | string \| null | The feast id this office is the vigil of. |
| `octaveOf` | null | Reserved (octave layer). |
| `aliases` | null | Reserved (`IdentityAliases`). |
| `citations` | null | Reserved (provenance/authority). |
| `text` | null | Reserved (Missal proper texts). |
| `chant` | null | Reserved (GABC). |
| `audio` | null | Reserved. |

### i18n & content hooks

Human-readable text is i18n-shaped, never baked: `names` is a locale-keyed map
with the invariant `la` (the liturgical Latin, not a "translation") always
present. v1.0 ships Latin only; the text layer (v1.1) adds vernacular locales as
further keys without reshaping anything. The proper-text pipelines attach through
reserved, nullable office hooks — `text` (Missal propers), `chant` (GABC), `audio`
— alongside `citations`; all are null in 1.0 and only ever filled. The engine
hard-codes no language text: every name comes from the corpus data.

### Stable identifiers (a compatibility surface)

`id` is the bare `ObservanceId` slug — identity only, edition-invariant, and
independent of date or rank. It **is** the stable cross-system feast id: the
"mapping from observance id to stable feast id" is the identity function, so no
separate registry is needed. `urn` is the same id under the platform URN scheme
(`introibo:observance:<id>`), and round-trips: stripping the prefix and parsing
yields the identical id.

These identifiers are a **compatibility surface** and are guaranteed stable:

- An id, once published, is never renamed or re-homed. Later editions
  (1954/1955, monastic) reuse the same ids for the same feast.
- A renamed *display* name never moves the id (names are separate, i18n-keyed).
- Identity lineage — a feast split into two, or two merged — is expressed in the
  reserved `aliases` slot, never by changing an existing id.

A golden-list test (`tests/Contract/StableIdentifierTest.php`) pins a
representative set (temporal Sunday and feria, a sanctoral feast, a sanctoral
vigil) so any accidental change to a published identifier fails loudly.

### Transfer links

When a first-class feast is impeded, it appears in `displaced` on the impeded day
with `outcome: transfer` and `transferredTo` pointing at where it lands; on the
landing day it is the `celebration` with `transferredFrom` pointing back. These
two links are stamped by the resolver's reconciliation pass once the whole year
is known, so a consumer never has to correlate days.

### Derived fields deliberately dropped

`isVigil` (⇔ `vigilOf != null`), `isTransferred` (⇔ `transferredFrom != null`),
and a raw precedence `tier` (context-derived, not a stable office property — hence
`rankOrdinal` instead) are not shipped: they are derivable and would be redundant
surface to keep stable.

## Determinism & ordering

Same inputs produce byte-identical JSON. Within each role, offices keep the
resolver's deterministic order (precedence tier, then canonical id). A golden
snapshot test pins the full shape for a sample day, and focused snapshots pin the
transfer, omission, and commemoration cases.

## Worked example — a simple day

`contract()` for **2025-07-15** (an ordinary green feria of the time after
Pentecost; the feria is both the celebration and the tempora):

```json
{
  "contractVersion": "1.0.0",
  "corpusVersion": "1962-seed-2026-07-02",
  "engineVersion": "0.4.0",
  "rite": "roman",
  "edition": "roman:rubricae-1960",
  "date": "2025-07-15",
  "season": "pentecost",
  "commemorationLimit": 2,
  "celebration": [
    {
      "id": "roman:temporale:paschal:pentecost-time:week-5:feria-3",
      "urn": "introibo:observance:roman:temporale:paschal:pentecost-time:week-5:feria-3",
      "role": "celebration",
      "kind": "feria",
      "rank": "IV",
      "rankOrdinal": 4,
      "season": "pentecost",
      "colour": { "base": "green", "roseAllowed": false },
      "names": { "la": "Feria III infra Hebdomadam V post Octavam Pentecostes" },
      "titulars": [],
      "outcome": null,
      "transferredTo": null,
      "transferredFrom": null,
      "vigilOf": null,
      "octaveOf": null,
      "aliases": null,
      "citations": null,
      "text": null,
      "chant": null,
      "audio": null
    }
  ],
  "commemoration": [],
  "displaced": [],
  "tempora": [ { "…": "the same office, role: tempora" } ],
  "secondVespers": {
    "outcome": "full-of-following",
    "favoursFollowing": true,
    "holder": null,
    "commemorated": null
  },
  "firstVespers": null,
  "resolution": null,
  "fasting": null,
  "calendar": null
}
```

A worked **transfer** example (St Joseph impeded by the Third Sunday of Lent in
2017, landing on the Monday) and the full versioning bump-rules follow in #58.
