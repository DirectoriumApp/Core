# The output contract

The versioned, serialisable shape that `Directorium\Core\contract()` emits — the
**public contract** the Api, Site, and Ordo repos build on. Epic #52.

The engine resolves a civil date to a `Calendar\LiturgicalDay` (Epic #29). That
aggregate is kept pure: it holds value objects and knows nothing about JSON. A
separate serialiser, `Contract\DayContract`, turns it into a stable, JSON-ready
structure. The shape is **frozen on the 1.0 line** (currently `1.0.2`) and only
ever grows additively (reserved slots fill as patch bumps; keys are added, never
removed or repurposed). This document is the spec downstream teams build against.

## Where it lives

- **`Contract\DayContract`** — `from(LiturgicalDay, Provenance): self`, then
  `toArray(): array` (JSON-ready) or `toJson(): string`. `const SHAPE_VERSION`
  is the contract shape's SemVer.
- **`Contract\Provenance`** — the three provenance axes (below).
- **`Directorium\Core\contract(DateTimeImmutable): array`** — the public entry
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
| `contractVersion` | `DayContract::SHAPE_VERSION` | SemVer of the **shape** (1.0.2). |
| `corpusVersion` | `SanctoralData::version()` (seed: `1962-seed-<date>`) | The **calendar data** build; carries no edition token. |
| `engineVersion` | `Directorium::VERSION` | The **resolver** version; hand-bumped when output changes. |

The **edition** (`roman:rubricae-1960`) is the rules-family that governed the
resolution, and **rite** (`roman`) is its leading segment. The same corpus can
be resolved under different editions, which is why corpus and edition are
separate axes.

`edition` **is the active rubric-system stamp** (#74): it names, per result,
which of the platform's rubric systems — 1962 (`roman:rubricae-1960`), 1954
(`roman:divino-afflatu`), or 1955 (`roman:rubricae-1955`) — produced the day, so
a consumer never has to guess. It is a stable identifier (the `RubricSystem`
URN), not a display label; the human label lives on the Api's `/meta` discovery,
keyed by this URN. When a caller names no rubric system the field is
`roman:rubricae-1960`, so an existing 1962 consumer is unaffected — the stamp is
additive and backward-compatible. (Only 1962 is resolvable through the public
`day()`/`contract()` boundary today; 1954/1955 are stamped the same way when
resolved through `DayResolver::forEdition()`.)

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
- **Open** (a new member is a *minor* change):
  - `season` — **reclassified from closed to open** before the 1.0 freeze (see
    [`season`: open, edition-scoped vocabulary](#season-open-edition-scoped-vocabulary)).
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

The 1.0 shape is frozen: `ContractShapeTest` pins the key layout above and the
golden-year digest pins the resolved values across the centuries, so a field cannot be
removed, renamed, or reordered without a deliberate, reviewed change. How this relates to
the PHP API and how versions move together is [`api-stability.md`](../api-stability.md).

### `season`: open, edition-scoped vocabulary

`season` is being **reclassified from a closed 8-member enum to an open,
edition-scoped vocabulary** before the 1.0 contract freeze (tracking issue #364,
which blocks the contract-promotion issue #90). The closed set could not admit
the Novus Ordo's `ordinary-time` without a major bump, and freezing it would
force exactly the breaking change v1 exists to avoid.

The rule keeps cross-edition comparison honest:

- **Tokens stay bare and shared where the concept is shared.** `advent`, `lent`,
  and `eastertide` mean the same liturgical thing across editions, so the token
  is identical and the diff engine aligns by token — no per-edition remapping.
- **Each edition declares its valid subset.** The Novus Ordo adds
  `ordinary-time`; the traditional subset (`advent` · `christmastide` ·
  `epiphany` · `septuagesima` · `lent` · `passiontide` · `eastertide` ·
  `pentecost`) is **unchanged**, and every edition built today (1954/1955/1962)
  shares it — so the reclassification is source-compatible for every edition
  shipped today.
- **Adding a vocabulary entry is a minor bump**, per the open-enum rule above.

Per-edition subsets and the registry live in
[`season-vocabulary.md`](season-vocabulary.md); this contract fixes only that the
field is open and that shared concepts share a token.

## The day shape

| Field | Type | Source | Notes |
| --- | --- | --- | --- |
| `contractVersion` | string | `SHAPE_VERSION` | `1.0.2`. |
| `corpusVersion` | string | `SanctoralData::version()` | e.g. `1962-seed-2026-07-02`. |
| `engineVersion` | string | `Directorium::VERSION` | e.g. `0.4.0`. |
| `rite` | string | edition head | `roman`. |
| `edition` | string | `Provenance` | The **active rubric system** (#74): `roman:rubricae-1960` (1962), `roman:divino-afflatu` (1954), or `roman:rubricae-1955` (1955). Defaults to `roman:rubricae-1960`. |
| `date` | string | resolved date | ISO-8601 `Y-m-d`. |
| `season` | string \| null | the temporal office | Open, edition-scoped `season` vocabulary; null on a placeholder. See [`season`: open, edition-scoped vocabulary](#season-open-edition-scoped-vocabulary). |
| `commemorationLimit` | int | `PrecedenceRules::commemorationClassLimit` (per edition) | Commemorations admitted by the day's **class under the active edition** (#332) — 1962: I/II 1, III/IV 2; 1954: 3 for every class; 1955: 0/1/2 by class. 0 when nothing is celebrated. This is the class-level cap; the days that admit no commemoration at all (Triduum, privileged octaves, first-class vigils) are distinguished only in the [`resolution` trace](#reserved-slots), so the field stays a stable property of the day's class. |
| `celebration` | office[] | `LiturgicalDay` | The office celebrated (normally one). |
| `commemoration` | office[] | `LiturgicalDay` | Offices commemorated within it. |
| `displaced` | office[] | `LiturgicalDay` | Offices impeded this day (transferred or omitted). |
| `tempora` | office[] | `LiturgicalDay` | The temporal office of the season, always reported. |
| `secondVespers` | object \| null | `ConcurrenceOutcome` | The evening concurrence (below); null when unresolved. |
| `firstVespers` | null | reserved | Office layer. |
| `resolution` | object \| null | opt-in | The "why-this-won" trace (#233); null by default, filled by `explain()` / `contract($d, true)`. See resolution-trace-model.md. |
| `fasting` | object \| null | filled | The day's fast/abstinence obligation under the active penitential discipline (#250); null on a day that carries none. Sub-shape below. |
| `calendar` | object | filled | Always carries `astronomical` — the day's calendrical/astronomical block (#242/#245). Also carries `particular` when resolved under a particular calendar (#78), and the reserved `lectionary`. Sub-shape below. |

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
| `urn` | string | id | `directorium:observance:<id>`. |
| `role` | string | `CelebrationRole` | Closed enum. |
| `kind` | string | `ObservanceKind` | Open enum. |
| `rank` | string | `RankClass` | `I`–`IV`. |
| `rankOrdinal` | int | `RankClass` | 1 (highest) – 4. |
| `season` | string \| null | `TemporalObservance` | Open, edition-scoped vocabulary; set on temporal offices, null on sanctoral. |
| `colour` | object | `ElementColour` | `{ base, roseAllowed, alternates? }` (below). |
| `names` | object | `Observance::names()` | Locale → name; `la` always present. |
| `titulars` | string[] | `Observance::titulars()` | Titular subjects (sanctoral); `[]` for temporal. |
| `outcome` | string \| null | `OccurrenceOutcome` | Closed enum; null on a celebration or the tempora. |
| `transferredTo` | string \| null | resolver | ISO date a displaced (impeded) office moved to. |
| `transferredFrom` | string \| null | resolver | ISO date a landed office was transferred from. |
| `vigilOf` | string \| null | `SanctoralObservance::vigilOfId()` | The feast id this office is the vigil of. |
| `octaveOf` | null | reserved | Octave layer. |
| `aliases` | null | reserved | `IdentityAliases` lineage. |
| `citations` | null | reserved | Provenance/authority. |
| `optionality` | null | reserved | Choice-day additive key (below); null unless the day offers alternatives. |
| `text` | null | reserved | Missal proper texts; availability-aware fill-shape (below). |
| `chant` | null | reserved | GABC; same availability-aware fill-shape as `text`. |
| `audio` | null | reserved | Audio. |

### i18n & content hooks

Human-readable text is i18n-shaped, never baked: `names` is a locale-keyed map
with the invariant `la` (the liturgical Latin, not a "translation") always
present. v1.0 ships Latin only; the text layer (v1.1) adds vernacular locales as
further keys without reshaping anything. The proper-text pipelines attach through
reserved, nullable office hooks — `text` (Missal propers), `chant` (GABC), `audio`
— alongside `citations`. The engine hard-codes no language text: every name comes
from the corpus data.

### Text & chant fill-shape (availability-aware)

The `text` and `chant` slots ship `null` in 1.0, but the **shape they fill with**
is fixed now, because the first fill (the v1.2 text layer) must not lock a
structure that copyrighted post-1962 texts cannot inhabit. A copyrighted proper
can be shipped only as an incipit or a citation, so the shape has to carry the
string's availability as a first-class field rather than assuming a full body is
always present.

When filled, each slot is keyed **per role and per locale**; every leaf string is
an object of the shape:

```json
{ "value": "…", "availability": "full", "source": "directorium:source:…", "rights": "…" }
```

- `value` **or** `incipit` — the full text (`availability: full`) or the opening
  words only; never both. A citation-only or licence-required leaf carries
  `incipit` (or neither) but no `value`.
- `availability` ∈ `full` · `incipit` · `citation-only` · `licence-required` —
  what the reader and the comparison tool may render for this leaf.
- `source` — the provenance URN (`directorium:source:<key>`), tying the leaf to the
  source registry.
- `rights` — the licence/PD status governing the leaf.

`availability` and the CC0-vs-copyright rule that drives it are specified in
[`text-licensing-model.md`](text-licensing-model.md); the diff renderer keys its
per-side degradation off this field.

### Choice-days & optionality (Novus Ordo)

Some days legitimately offer the celebrant a choice — a Novus Ordo weekday may be
kept as the feria **or** as an optional memorial. This is modelled **additively,
never by widening a closed enum**:

- The alternatives are **multiple `celebration` entries** (the feria *and* each
  optional memorial), each a normal, self-describing office.
- An additive, reserved **`optionality`** key on each such office marks it as one
  arm of a choice and how the arms relate (e.g. free choice among memorials, or
  memorial-over-feria). It is `null` on ordinary days.

The closed `role` and `outcome` enums are **untouched**: a chosen-or-not office is
still a `celebration`, and no new `role`/`outcome` member is minted. A 1962-only
consumer that ignores `optionality` still reads a coherent (feria-first) day.

### The `calendar` sub-shape

The day-level `calendar` block groups calendar-scoped facts. It is **always an
object** — every day carries the `astronomical` block — with `particular` and the
reserved `lectionary` appearing when they apply.

**`astronomical` — the calendrical block (#242/#245, filled now).** The cyclic
figures printed at the head of an ordo or in the front matter of the martyrology,
with the day's ecclesiastical lunar age. Edition-invariant — a pure function of the
date, computed from `Calendrical\CalendricalYear` and `Calendrical\LunarAge`:

```json
"calendar": {
  "astronomical": {
    "goldenNumber": 12, "epact": 0, "solarCycle": 18,
    "dominicalLetter": "E", "romanIndiction": 3, "lunarAge": 18
  }
}
```

- `goldenNumber` 1–19 · `epact` 0–29 (the moon's age at the head of the year; 0 is
  printed as `*`) · `solarCycle` 1–28 · `romanIndiction` 1–15.
- `dominicalLetter` — one letter `A`–`G`, or two in a leap year (e.g. `GF`), the
  second governing March onward.
- `lunarAge` — the ecclesiastical (schematic) moon's age, 1–30; `14` is the full
  moon. It is anchored to the paschal lunation, so Luna 14 falls on the
  ecclesiastical paschal full moon exactly, every year; see `Calendrical\LunarAge`
  and KNOWN-LIMITATIONS for the civil-year-boundary caveat.

**`particular` — the selected particular calendar (#78).** When a caller resolves
under a particular calendar (an `overlay`: SSPX, FSSP, a diocese), the block also
names it:

```json
"calendar": {
  "particular": { "id": "directorium:overlay:roman:sspx", "name": "Society of Saint Pius X" },
  "astronomical": { "…": "as above" }
}
```

- `particular.id` is the overlay's platform URN; `particular.name` its display name.
- The key is **absent** under the universal 1962 calendar (the `calendar` block
  itself is never null now — it still carries `astronomical`). The overlay is *also*
  reflected in `corpusVersion` (`base+overlayId`, the cache-key axis); `particular`
  is the structured, human-readable counterpart.

**`lectionary` — reserved.** Joins the block alongside `astronomical`:

```json
"calendar": {
  "lectionary": { "sundayCycle": "A", "weekdayCycle": "II" }
}
```

- `lectionary.sundayCycle` ∈ `A` · `B` · `C` (nullable).
- `lectionary.weekdayCycle` ∈ `I` · `II` (nullable).

Both are **nullable and edition-conditional**: the 1962 edition has no cycle
lectionary, so the whole `lectionary` block is null there; a Novus Ordo snapshot
fills it. Nesting these under `calendar` (rather than at the day root) keeps the
day-level key count stable and groups them with the other calendrical facts.

### The `fasting` sub-shape

The day-level `fasting` block (#248/#250) reports the fast/abstinence obligation. It
is `null` on a day that carries none (most days), and an object when some fast or
abstinence applies:

```json
"fasting": {
  "fast": true,
  "abstinence": "full",
  "discipline": "roman:cic-1917",
  "reason": "lent-major",
  "citation": "cic-1917:c1252"
}
```

- `fast` — whether the day is a day of fast (one full meal).
- `abstinence` ∈ `full` (no flesh meat) · `partial` (flesh at the principal meal only)
  · `none`.
- `discipline` — the governing discipline's URN. Fasting is **canon law, not rubric**,
  so it sits on its own axis: a single discipline (the 1917 Code, `roman:cic-1917`)
  governs the 1954/1955/1962 editions alike, mapped from the edition by
  `RubricSystem::penitentialDiscipline()`. A later era (Paenitemini, the modern norms)
  is a new discipline, additively.
- `reason` — the cited rule that applied (`ash-wednesday`, `ember-day`, `vigil`,
  `lent-major`, `lent-minor`, `friday`); `citation` its authority.

The obligation is a **per-day realization**, computed from the resolved day's own
properties (weekday, season, an office's `kind`/`id`), so per-edition correctness is
emergent — a vigil an edition suppressed is not a vigil day and carries no fast; the
discipline is never duplicated per edition. See `penitential-discipline-model.md`.

### Stable identifiers (a compatibility surface)

`id` is the bare `ObservanceId` slug — identity only, edition-invariant, and
independent of date or rank. It **is** the stable cross-system feast id: the
"mapping from observance id to stable feast id" is the identity function, so no
separate registry is needed. `urn` is the same id under the platform URN scheme
(`directorium:observance:<id>`), and round-trips: stripping the prefix and parsing
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

**Colour alternates.** `colour` carries an additive, **open** `alternates` list
that generalises the rose/`roseAllowed` pattern to any permitted-alternate colour
— for instance the Spanish/Latin-American **blue privilege** on the Immaculate
Conception, a genuine liturgical alternate that the five-colour base set cannot
express. `colour.base` stays a **closed** 5-member enum; `alternates` is an open
list of additional permitted colours (with the same "a celebrant may, not must"
force as rose). Because it is additive and open, adding an alternate — or adding
the list to a colour that previously lacked it — is never a major bump. `alternates`
is absent/empty where no alternate applies; `roseAllowed` is retained as the named,
rubric-specific case rather than folded into the list.

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
| `calendar.lectionary` | day | Lectionary cycles (Novus Ordo) — `calendar.astronomical` (#242) and `fasting` (#250) are filled |
| `octaveOf` | office | Octave modelling |
| `aliases` | office | `IdentityAliases` lineage |
| `citations` | office | Provenance/authority subsystem |
| `optionality` | office | Choice-day marker (Novus Ordo optional memorials) |
| `text` | office | Missal proper texts (v1.2), availability-aware fill-shape |
| `chant` | office | Gregorian chant / GABC, availability-aware fill-shape |
| `audio` | office | Audio |

The day-level `calendar` slot's planned sub-shape (`lectionary`) is documented
under [The `calendar` sub-shape](#the-calendar-sub-shape).

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
  "contractVersion": "1.0.2",
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
      "urn": "directorium:observance:roman:temporale:paschal:pentecost-time:week-5:feria-3",
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
  "calendar": {
    "astronomical": {
      "goldenNumber": 12,
      "epact": 0,
      "solarCycle": 18,
      "dominicalLetter": "E",
      "romanIndiction": 3,
      "lunarAge": 18
    }
  }
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
