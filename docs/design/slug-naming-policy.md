# Slug naming policy

_Status: governance (issue #9). Slugs are immutable once published, so this policy must be settled before
bulk sanctoral authoring (#24 / #41). The maintainer is the naming authority._

## Principles

1. **Latin, transliterated.** The subject slug is the Latin name, lowercased and transliterated to ASCII
   (`æ → ae`, `ë → e`, `ç → c`), words joined by hyphen: `laurentius`, `thomas-aquinas`, `maria-assumptio`.
2. **Minimal — title only when needed.** The bare Latin name is the slug. A disambiguator is added **only
   when two subjects would otherwise collide**, in this order of preference:
   1. the traditional Latin **epithet / byname** — `ioannes-a-cruce` (John of the Cross),
      `felix-nolanus` vs `felix-romanus`;
   2. for two celebrations of one person, the Latin **occasion** —
      `ioannes-baptista-nativitas` vs `ioannes-baptista-decollatio`;
   3. as a last resort, an English **title word** — `felix-martyr` vs `felix-confessor`.
3. **Composite feasts are one slug.** Several saints honoured as a single observance join into one slug:
   `petrus-paulus`, `cosmas-damianus`. The individual subjects are recorded as the observance's titulars.
4. **Proper calendars use a provenance prefix.** A feast proper to an order/region carries a leading
   provenance segment: `osb:benedictus` (universal `benedictus` stays unprefixed).

## What a slug never encodes

Rank, colour, octave, date, edition, or year — those are per-edition attributes or realization, never
identity (see [the ID grammar](observance-id-grammar.md)).

## Governance

- Slugs are **immutable** once published; a rename is a split (`IdentityAliases`), never an edit.
- A lint check (slug charset + collision detection against the published set) lands with the first
  large-scale sanctoral data entry (#24 / #41).
- New slugs are reviewed by the maintainer against this policy before they enter the corpus.
