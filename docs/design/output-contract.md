# The output contract

The versioned, serialisable shape that `Introibo\Core\contract()` emits — the
**public contract** the Api, Site, and Ordo repos build on. Epic #52.

The engine resolves a civil date to a `Calendar\LiturgicalDay` (Epic #29). That
aggregate is kept pure: it holds value objects and knows nothing about JSON. A
separate serialiser, `Contract\DayContract`, turns it into a stable, JSON-ready
structure. The shape is **frozen at contract version 1.0.0** and only ever grows
additively (reserved slots fill; keys are added, never removed or repurposed).
This document is the spec downstream teams build against.

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

## Versioning & stability

Every day carries three **independent** version stamps, so a consumer keys a
cache on all three — any one moving means the resolved output may differ:

| Field | Source | Meaning |
| --- | --- | --- |
| `contractVersion` | `DayContract::SHAPE_VERSION` | SemVer of the **shape** (1.0.0). |
| `corpusVersion` | `SanctoralData::version()` (seed: `1962-seed-<date>`) | The **calendar data** build; carries no edition token. |
| `engineVersion` | `Introibo::VERSION` | The **resolver** version; hand-bumped when output changes. |

The **edition** (`roman:rubricae-1960`) is the rules-family that governed the
resolution, and **rite** (`roman`) is its leading segment. The same corpus can
be resolved under different editions, which is why corpus and edition are
separate axes.

### Closed vs open enums

A value's enum being **closed** or **open** is what makes a change breaking or
additive (see bump rules):

- **Closed** (a new member is a *major* change):
  - `role` — `celebration` · `commemoration` · `displaced` · `tempora`
  - office `outcome` — `commemorate` · `transfer` · `omit`
  - `secondVespers.outcome` — `full-of-preceding` · `preceding-commem-following` ·
    `following-commem-preceding` · `full-of-following`
  - `colour.base` — `white` · `red` · `green` · `violet` · `black` (rose is never
    a base — see [Colour & rose](#colour--rose))
  - `season` — `advent` · `christmastide` · `epiphany` · `septuagesima` · `lent` ·
    `passiontide` · `eastertide` · `pentecost`
- **Open** (a new member is a *minor* change):
  - office `kind` — the `ObservanceKind` set (`feast`, `feria`, `sunday`, `vigil`,
    `octave-day`, `within-octave`, `ember-day`, `rogation-day`, `special-movable`,
    `lady-on-saturday`, `commemoration-only`, `office-of-the-dead`), extensible as
    editions add species.
  - `names` locale keys, `rite`, `edition`.

### Bump rules

- **Patch** — a reserved slot goes `null` → populated in a tolerated way (a
  consumer that ignored the null still works).
- **Minor** — a new optional key is added, or an **open** enum gains a member.
- **Major** — a key is removed, renamed, or repurposed, or a **closed** enum
  gains a member. Frozen: no major change before v2.

### The stability promise

- Published `id`s / `urn`s never change or are re-homed; identity lineage lives in
  the reserved `aliases` slot, never by mutating an id.
- Reserved slots are only ever *filled*, never removed.
- Fields are only added, never repurposed.
- Within each role, offices are ordered deterministically (precedence tier, then
  canonical id) and pinned by snapshot.

## The day shape

| Field | Type | Source | Notes |
| --- | --- | --- | --- |
| `contractVersion` | string | `SHAPE_VERSION` | `1.0.0`. |
| `corpusVersion` | string | `SanctoralData::version()` | e.g. `1962-seed-2026-07-02`. |
| `engineVersion` | string | `Introibo::VERSION` | e.g. `0.4.0`. |
| `rite` | string | edition head | `roman`. |
| `edition` | string | `Provenance` | `roman:rubricae-1960`. |
| `date` | string | resolved date | ISO-8601 `Y-m-d`. |
| `season` | string \| null | the temporal office | Closed `season` enum; null on a placeholder. |
| `commemorationLimit` | int | `CommemorationLimit::forDayClass` | Commemorations admitted by the day's class (I/II: 1, III/IV: 2); 0 when nothing is celebrated. |
| `celebration` | office[] | `LiturgicalDay` | The office celebrated (normally one). |
| `commemoration` | office[] | `LiturgicalDay` | Offices commemorated within it. |
| `displaced` | office[] | `LiturgicalDay` | Offices impeded this day (transferred or omitted). |
| `tempora` | office[] | `LiturgicalDay` | The temporal office of the season, always reported. |
| `secondVespers` | object \| null | `ConcurrenceOutcome` | The evening concurrence (below); null when unresolved. |
| `firstVespers` | null | reserved | Office layer. |
| `resolution` | null | reserved | The "why-this-won" trace. |
| `fasting` | null | reserved | Fasting/abstinence layer. |
| `calendar` | null | reserved | Calendrical/astronomical block. |

The **`secondVespers`** object is `{ outcome, favoursFollowing, holder,
commemorated }`: `outcome` is the closed `ConcurrenceOutcome` value,
`favoursFollowing` a bool; `holder`/`commemorated` are reserved for the Office
layer (null in 1.0).

## The office shape

Each office is self-describing: identity, per-edition attributes, and how it
fared this day.

| Field | Type | Source | Notes |
| --- | --- | --- | --- |
| `id` | string | `ObservanceId` | The **stable** cross-system id. |
| `urn` | string | id | `introibo:observance:<id>`. |
| `role` | string | `CelebrationRole` | Closed enum. |
| `kind` | string | `ObservanceKind` | Open enum. |
| `rank` | string | `RankClass` | `I`–`IV`. |
| `rankOrdinal` | int | `RankClass` | 1 (highest) – 4. |
| `season` | string \| null | `TemporalObservance` | Set on temporal offices; null on sanctoral. |
| `colour` | object | `ElementColour` | `{ base, roseAllowed }` (below). |
| `names` | object | `Observance::names()` | Locale → name; `la` always present. |
| `titulars` | string[] | `Observance::titulars()` | Titular subjects (sanctoral); `[]` for temporal. |
| `outcome` | string \| null | `OccurrenceOutcome` | Closed enum; null on a celebration or the tempora. |
| `transferredTo` | string \| null | resolver | ISO date a displaced (impeded) office moved to. |
| `transferredFrom` | string \| null | resolver | ISO date a landed office was transferred from. |
| `vigilOf` | string \| null | `SanctoralObservance::vigilOfId()` | The feast id this office is the vigil of. |
| `octaveOf` | null | reserved | Octave layer. |
| `aliases` | null | reserved | `IdentityAliases` lineage. |
| `citations` | null | reserved | Provenance/authority. |
| `text` | null | reserved | Missal proper texts. |
| `chant` | null | reserved | GABC. |
| `audio` | null | reserved | Audio. |

### i18n & content hooks

Human-readable text is i18n-shaped, never baked: `names` is a locale-keyed map
with the invariant `la` (the liturgical Latin, not a "translation") always
present. v1.0 ships Latin only; the text layer (v1.1) adds vernacular locales as
further keys without reshaping anything. The proper-text pipelines attach through
reserved, nullable office hooks — `text` (Missal propers), `chant` (GABC), `audio`
— alongside `citations`. The engine hard-codes no language text: every name comes
from the corpus data.

### Stable identifiers (a compatibility surface)

`id` is the bare `ObservanceId` slug — identity only, edition-invariant, and
independent of date or rank. It **is** the stable cross-system feast id: the
"mapping from observance id to stable feast id" is the identity function, so no
separate registry is needed. `urn` is the same id under the platform URN scheme
(`introibo:observance:<id>`), and round-trips: stripping the prefix and parsing
yields the identical id.

These identifiers are a **compatibility surface** and are guaranteed stable: an
id, once published, is never renamed or re-homed; later editions (1954/1955,
monastic) reuse the same ids; a renamed *display* name never moves the id; and
identity lineage (a feast split, or two merged) is expressed in the reserved
`aliases` slot. A golden-list test (`tests/Contract/StableIdentifierTest.php`)
pins a representative set so any accidental change fails loudly.

### Transfer links

When a first-class feast is impeded it appears in `displaced` on the impeded day
with `outcome: transfer` and `transferredTo` pointing at where it lands; on the
landing day it is the `celebration` with `transferredFrom` pointing back. Both
links are stamped by the resolver's reconciliation pass once the whole year is
known — following each feast's *chain* of appearances, so a re-transfer cascade
or a feast celebrated on its own date link correctly — and a consumer never has
to correlate days.

### Colour & rose

A day is **not** a single colour: the principal office and each commemoration
each carry their own, so colour is per office element, not per day. `colour.base`
is a closed enum of the five base colours. **Rose is never a base**: Gaudete and
Laetare are violet days on which rose vestments are *permitted*, expressed as
`roseAllowed: true` on a violet base. Whether rose is actually worn is a
celebrant's choice, not calendar data, so no single "effective" colour is
derived.

### Derived fields deliberately dropped

`isVigil` (⇔ `vigilOf != null`), `isTransferred` (⇔ `transferredFrom != null`),
and a raw precedence `tier` (context-derived, not a stable office property —
hence `rankOrdinal`) are not shipped: they are derivable and would be redundant
surface to keep stable.

## Day boundary & First Vespers

The liturgical office day begins at **First Vespers** the evening before its
civil date and ends at the following day's First Vespers. The contract expresses
this so a client renders the right day at the right time:

- **`secondVespers`** reports how this day's Second Vespers concurs with the next
  day's First Vespers. `favoursFollowing: true` means the evening already belongs
  to the following office day (e.g. the eve of a first-class feast). Concurrence
  resolution depends on this boundary.
- **Vigils** sit on their own (preceding) civil date, with `kind: vigil` and
  `vigilOf` naming the feast they anticipate. That placement *is* how "a vigil
  attaches to the morrow" is expressed — the anticipated feast is celebrated on
  the next day and is **not** duplicated into the vigil day's payload.
- **`firstVespers`** is a reserved day-level slot the Office layer (v1.1) fills
  with the First Vespers actually said this evening; it is null in 1.0.

## Reserved slots

Every planned feature layer attaches through a slot that ships as `null` now, so
adding it is additive (a patch bump):

| Slot | Level | Filled by |
| --- | --- | --- |
| `firstVespers` | day | Office layer (v1.1) |
| `resolution` | day | Show-your-work resolution trace |
| `fasting` | day | Fasting & abstinence layer |
| `calendar` | day | Calendrical/astronomical block |
| `octaveOf` | office | Octave modelling |
| `aliases` | office | `IdentityAliases` lineage |
| `citations` | office | Provenance/authority subsystem |
| `text` | office | Missal proper texts (v1.2) |
| `chant` | office | Gregorian chant / GABC |
| `audio` | office | Audio |

## Determinism & ordering

Same inputs produce byte-identical JSON. Within each role, offices keep the
resolver's deterministic order (precedence tier, then canonical id). Golden
snapshot tests pin the full shape for a sample day and the transfer, omission,
commemoration, i18n, identifier, and boundary cases.

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
      "octaveOf": null, "aliases": null, "citations": null,
      "text": null, "chant": null, "audio": null
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

## Worked example — a transfer (complex day)

St Joseph (19 March, first class) is impeded by the Third Sunday of Lent in 2017
and transferred to the following Monday. The two ends link across days.

**2017-03-19** — the Sunday is celebrated; St Joseph is displaced with `outcome:
transfer` pointing at the Monday:

```json
{
  "date": "2017-03-19",
  "season": "lent",
  "commemorationLimit": 1,
  "celebration": [
    { "id": "roman:temporale:paschal:lent-3", "kind": "sunday", "rank": "I",
      "colour": { "base": "violet", "roseAllowed": false }, "outcome": null }
  ],
  "commemoration": [],
  "displaced": [
    { "id": "roman:sanctorale:ioseph", "kind": "feast", "rank": "I",
      "titulars": ["ioseph"], "outcome": "transfer",
      "transferredTo": "2017-03-20", "transferredFrom": null }
  ],
  "tempora": [ { "id": "roman:temporale:paschal:lent-3", "role": "tempora" } ],
  "secondVespers": { "outcome": "preceding-commem-following", "favoursFollowing": false }
}
```

**2017-03-20** — St Joseph lands and is celebrated with `transferredFrom` pointing
back at the Sunday; the Monday's Lenten feria is commemorated:

```json
{
  "date": "2017-03-20",
  "season": "lent",
  "commemorationLimit": 1,
  "celebration": [
    { "id": "roman:sanctorale:ioseph", "kind": "feast", "rank": "I",
      "titulars": ["ioseph"], "outcome": null,
      "transferredTo": null, "transferredFrom": "2017-03-19" }
  ],
  "commemoration": [
    { "id": "roman:temporale:paschal:lent-week-3:feria-2", "kind": "feria",
      "rank": "III", "outcome": "commemorate" }
  ],
  "displaced": [],
  "tempora": [ { "id": "roman:temporale:paschal:lent-week-3:feria-2", "role": "tempora" } ]
}
```

*(Fields elided for brevity are present exactly as in the simple-day example and
are pinned in full by `tests/Contract/DayContractTest.php`.)*
