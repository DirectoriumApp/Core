# Platform URN scheme

_Status: reserved (issue #9). This fixes one identity pattern for the whole platform so later
entity types — texts, chant, Ordo Missae units, the Martyrology — inherit it instead of inventing
their own. `ObservanceId` is its first citizen._

## Pattern

```
introibo:<entity-type>:<entity-native-id>
```

The **entity-native-id** already carries the rite as its own leading segment (as `ObservanceId` does),
so the URN is simply the entity type prepended to the native identifier. Charset and separators match the
[ID grammar](observance-id-grammar.md): lowercase ASCII, digits, hyphen within a segment, colon between
segments.

## Reserved entity types

| Entity type | Native id shape | Example URN |
|-------------|-----------------|-------------|
| `observance` | `<rite>:<cycle>:<body>` | `introibo:observance:roman:sanctorale:laurentius` |
| `text` | `<rite>:<role>:<observance-body>` | `introibo:text:roman:collect:sanctorale:laurentius` |
| `rite-unit` | `<rite>:<book>:<structure-path>` | `introibo:rite-unit:roman:mass:ordo:kyrie` |
| `chant` | `<rite>:<genre>:<observance-body>` | `introibo:chant:roman:introit:sanctorale:laurentius` |
| `martyrology` | `<rite>:<entry-key>` | `introibo:martyrology:roman:08-10:laurentius` |
| `edition` | `<rite>:<rules-family>` | `introibo:edition:roman:rubricae-1960` |
| `source` | `<key>` | `introibo:source:mr-1962` |
| `lectionary` | `<rite>:<scheme>:<occasion-body>:<slot>` | `introibo:lectionary:roman:1970-sunday-a:advent:sunday-1:gospel` |
| `audio` | `<rite>:<genre>:<observance-body>` | `introibo:audio:roman:introit:sanctorale:laurentius` |
| `devotion` | `<rite>:<subject-slug>` | `introibo:devotion:roman:rosarium` |
| `overlay` | `<rite>:<overlay-slug>` | `introibo:overlay:roman:sspx` |

The `rite-unit` type is what the **comparison tool's rite mode** diffs: the Order of Mass modeled as an
ordered tree of URN'd parts, each with per-edition presence/text/rubric/chant.

- **`source`** was already minted by [`corpus-schema.md`](corpus-schema.md) (`introibo:source:<key>`,
  the provenance registry). Reserving it here fixes a live inconsistency — the type was in use before it
  was reserved — and adopts that document's grammar unchanged: the native id is the bare source key, with
  no rite segment (a source is not rite-scoped).
- **`lectionary`** addresses a single reading slot. Grammar:
  `introibo:lectionary:<rite>:<scheme>:<occasion-body>:<slot>`, where the **scheme** ∈ `1962` ·
  `1970-sunday-a` · `1970-sunday-b` · `1970-sunday-c` · `1970-weekday-1` · `1970-weekday-2` ·
  `1970-oor-1` · `1970-oor-2` (the Novus Ordo Sunday A/B/C and weekday I/II cycles, plus the
  two-year Office of Readings cycle); `<occasion-body>` is the occasion the reading serves, and `<slot>`
  the role within it (e.g. `first-reading`, `psalm`, `gospel`). The scheme is intrinsic to the reading —
  the same pericope in cycle A vs B is a different lectionary URN — so it belongs in the native id, not in
  resolution context.
- **`audio`** carries a rendered recording, mirroring the `chant` grammar (`<rite>:<genre>:<observance-body>`).
- **`devotion`** carries extra-liturgical devotions (the Rosary, litanies, the Angelus) that hang off the
  calendar but are not offices.
- **`overlay`** carries a **particular-calendar layer applied atop an edition** — SSPX, FSSP, and diocesan
  calendars. An overlay is **not an edition**: it does not restate the rubric family, it adds/removes/reranks
  observances over one. See [`edition-governance.md`](edition-governance.md) for how overlays, snapshots,
  and decrees relate.

## Translations are not URNs

A translation is **never a separate entity**. Vernacular renderings are **locale keys on the `text`
entity** (the `names`/text i18n map, `la` invariant), exactly as in the output contract. There is no
`introibo:translation:…` type and no per-locale URN: `introibo:text:roman:collect:sanctorale:laurentius`
is one identity whose `en`, `de`, … values are keyed inside it. This keeps a text's identity stable as
translations are added and keeps the diff engine aligning by identity, not by language.

## Edition & year

Following the identity / edition / year split, the URN names the **identity**; edition and year are
resolution *context*, not part of the identifier — **except** where an entity is intrinsically
edition-specific (a text that only exists in one edition, or an Ordo-Missae unit present in one rubric
system). In that case the edition is part of that entity's native id (e.g. a Novus-Ordo-only Eucharistic
Prayer), never bolted onto the identity of an entity shared across editions. Per-year facts are never in a
URN — they are realization.

## Invariants

- URNs are immutable and never reused; lineage (split/merge) is recorded in `IdentityAliases`, never by
  mutating an id.
- `introibo:observance:` + an `ObservanceId` round-trips to that same `ObservanceId`.
- External-system identifiers map in via `IdentityAliases::externalUrns()` (for the stable cross-system
  export, #55) — they are recorded, not adopted as the platform id.

## Rite-framework axes (reserved)

Two axes on the rite framework are reserved here so cross-rite entities inherit them, rather than each
rite inventing its own:

- **Calendar `reckoning`.** A rite carries a reckoning axis — **Gregorian**, **Julian**, or **Revised
  Julian** — and, where it reckons the moon by the old cycle, the Julian **Paschalion** (the traditional
  Eastern computus). This is what lets an Eastern rite compute Pascha on the Julian reckoning while the
  Roman engine stays Gregorian; the axis sits on the rite, not on any identifier.
- **Rite-neutral `function` correspondence tag.** Rite structural units (`rite-unit`) carry a
  rite-neutral **function** tag so cross-rite comparison aligns by *function*, not by name — e.g. the
  Roman **Canon** and the Byzantine **anaphora** both tag `function: anaphora`, and the diff aligns them
  even though nothing about their names matches. The name stays native to each rite; the function tag is
  the shared spine the comparison tool sorts on.
