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

The `rite-unit` type is what the **comparison tool's rite mode** diffs: the Order of Mass modeled as an
ordered tree of URN'd parts, each with per-edition presence/text/rubric/chant.

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
