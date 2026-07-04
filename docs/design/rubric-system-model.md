# The rubric-system (edition) model — Core v0.3 (#59, #63, #68, #72, #332)

_How the engine runs the **1954 (Divino Afflatu)**, **1955 (interim / Cum nostra hac aetate)**, and
**1962 (Rubricae 1960)** rubric systems from one codebase — 1962 being the only one built today. Builds on
the reserved edition axis ([`edition-governance.md`](edition-governance.md)), the per-edition precedence
seam ([`precedence-model.md`](precedence-model.md)), and the 3-layer corpus ([`corpus-schema.md`](corpus-schema.md))._

## The two orthogonal axes

The engine already has one selection axis and this epic adds a second; keeping them distinct is the whole
design:

1. **Rubric system / edition** (NEW — #59). The frozen rules-family + its calendar snapshot:
   `roman:divino-afflatu` (1954), `roman:rubricae-1955` (1955), `roman:rubricae-1960` (1962). Selecting an
   edition picks **both** the rules object (`Rubrics19XXPrecedence`) **and** the data directory
   (`data/corpus/editions/<edition>/`). This is the "which rubrics" axis.
2. **Particular calendar / overlay** (EXISTING — Epic #75). `universal | sspx | fssp | icksp` — an additive
   layer resolved *over* an edition ([`edition-governance.md`](edition-governance.md): "an overlay is never
   an edition"). This is the "whose calendar" axis.

They compose: `(1962, sspx)` is today's live 3mi.org path; `(1954, universal)` is the new work; `(1954,
sspx)` is expressible but semantically odd (SSPX uses the 1962 rubrics by definition) — the resolver allows
any pairing and lets the data decide, but `/meta` advertises the sensible combinations. **Default is
`roman:rubricae-1960`**, so every existing caller (Api v0.1, Ordo v0.1) is unaffected.

## Scope — v0.3 is calendar-level

The engine emits a **resolved calendar day**: the celebrated office (rank, colour, season, Latin name), its
commemorations, and the displaced/transferred offices. It does **not** emit the Divine Office or the Mass
ordo. The 1954 system carries a great deal of *Office-layer* casuistry — the *Tres Tabellae* concurrence at
Vespers (split "a capitulo de sequenti", the rite→solemnity→primary/secondary→personal-dignity→external
ladder), first/second Vespers assignment, the preces feriales, the Suffrage of the Saints, the Athanasian
Creed, the impeded feast's proper Gospel as Last Gospel. **All of that is recorded for fidelity but deferred
to the Office layer (Core v1.4+)** — exactly as the 1962 engine already reports concurrence only at the
"which office holds the evening" level (`precedence-model.md` §Concurrence). v0.3's job is the **calendar
rows**: rank scheme, octaves, vigils, commemoration counts, and occurrence precedence. This scoping is what
keeps the 1954 engine tractable.

**Calendar-level pre-1955 rules the 1954 engine must encode** (firm from the *Rubricae Generales* +
Quigley/Fortescue/1913 CE, to be regression-checked against the St. Lawrence Press pre-1955 Ordo):
- **Sundays — three tiers.** Greater I-class (Advent I; Lent I–IV; Passion; Palm; Easter; Low; Pentecost —
  no feast displaces; commemorations admitted **except** Easter & Pentecost). Greater II-class (Advent
  II–IV; Septuagesima/Sexagesima/Quinquagesima — only a **Double I class** displaces). Lesser/green *per
  annum* (displaced **only** by a Double of the I/II class or a feast of the Lord — the *Divino Afflatu*
  elevation of Sundays over ordinary doubles). An impeded Sunday is **commemorated, never
  anticipated/resumed**.
- **Ferias — privileged / major / minor.** Privileged (Ash Wednesday, Mon–Wed of Holy Week + the Triduum —
  office always kept). Major/greater (all Advent & Lent/Passiontide ferias, the Ember days, Rogation Monday
  — always **commemorated** even under the highest feast). Minor/ordinary green weekdays — **omitted**, no
  commemoration.
- **Vigils.** A common vigil outranks ferias & simples, yields to every nine-lesson office; **on a Sunday it
  is anticipated to the preceding Saturday** (the pre-1955 rule — 1955 changed this to "omitted"); impeded
  by a double/semidouble it is **commemorated**, by a first-class/solemn feast **dropped**. Major vigils
  (Christmas, Epiphany, Pentecost) have a proper office; the Christmas vigil is a Double from Lauds, violet,
  and **displaces the 4th Sunday of Advent**.
- **Transfer.** More generous than 1962: an impeded **double** (not only a first-class feast) is
  transferred to the next free day.
- **Commemorations.** Orations ≤ 3; multiple commemorations are normal (vs the 1955/1962 0/1/2 caps).

## Seam 1 — the `RubricSystem` selector (#60)

A `RubricSystem` value object under `src/Edition/` (PHP 7.4: `final class`, `private const`, guarded
factory — the `Rite`/`Cycle`/`AnchorFamily` pattern), holding the three known systems + a reserved slot.
Each carries: the **edition URN** (`roman:rubricae-1960`), the **corpus directory**
(`roman-rubricae-1960`), a **display label**, and its **validity window** (from `edition.json`). Threading:

- `functions.php` — append `?string $rubricSystem = null` (default → 1962) to `day()`, `contract()`,
  `resolvedYear()`, `explainedYear()`. **Appended last** so `day($date, $calendar)` stays
  backward-compatible for the Api/Ordo.
- `CalendarCatalog::resolver(?string $calendar, ?string $rubricSystem)` — resolves the pair.
- `DayResolver::forEdition(RubricSystem $rs, ?SanctoralData $overlayData)` replaces the 1962-hard-wired
  `for1962()` (kept as a thin `forEdition(RubricSystem::rubricae1960())` alias for BC).

Resolving a date **outside** the selected edition's validity window returns the day but **flags** it
(per `edition-governance.md` + `KNOWN-LIMITATIONS.md`) — never silently anachronistic.

## Seam 2 — de-hardcode the data loaders (#62)

The Explore map found the 1962 directory hard-coded in two constants:

- `CorpusSanctoralData` — `const EDITION_DIR = 'roman-rubricae-1960'` → take the edition dir as a
  constructor arg (from the `RubricSystem`).
- `PrecedenceTable` — same `EDITION_DIR` const → parameterise; it already reads
  `precedence.tiers.ndjson` + `precedence.rules.ndjson` per edition.

`Corpus::at($baseDir)` is already parameterisable; the shared `identity/` and `temporal/skeleton.ndjson`
are edition-invariant and reused unchanged (the physical 3-layer model — `corpus-schema.md`).

## Seam 3 — per-system rank & commemoration semantics (#61, #332)

- **Rank scheme.** `RankClass` (I–IV) is the normalised sort key; `LegacyRank` already models the pre-1960
  tokens (`duplex-i-classis`, `duplex-maius`, `semiduplex`, `simplex`, `dominica-maior`, …). 1954/1955
  attributes carry the `LegacyRank` token **and** a normalised `RankClass` for the tier sort; the mapping is
  data (§ "Rank mapping" below), not code. **Built (#64):** `attributes.sanctorale.ndjson` carries an optional
  `legacyRank` token — the author states both the token and its normalised numeric class, each cited to the
  1954 oracle (`ordo-1954`); `CorpusSanctoralData` reads it and threads it through `SanctoralEntry` →
  `SanctoralObservance`, ready for `Rubrics1954Precedence` (#67) to order the fine grades the four classes
  collapse. The generator materialises each non-base edition as a **diff** into its own dir (`meta.editions`
  + `transformSanctoraleEdition`), leaving the base 1962 pass — and its corpus bytes — untouched.
- **Commemoration limits.** Already per-edition data (`{rule:"commemoration-limit", dayClass, limit}`);
  1954 admits more than 1962. #332 is therefore mostly *data* + making `commemorationLimit()` read it per
  edition (it already does for 1962).
- **Temporal attributes per edition.** `attributes.temporale.ndjson` assigns rank/colour by **archetype**
  per edition, so the same procedural fillers yield edition-correct Sunday/feria ranks without branching —
  *provided the fillers read the archetype attributes rather than baking ranks.* If any ranks are currently
  hard-coded in the fillers, #61 moves them into `attributes.temporale.ndjson` (a refactor guarded by the
  1962 golden fixture).

**Foundation review notes (#64, 2026-07-04 adversarial pass — all non-blocking):**
- **Inheritance is Layer-1 only.** A non-base edition materialises its own Layer-2 (attributes) + placement in
  full; it does **not** re-declare identity — `CorpusSanctoralData` joins the per-edition attributes/placement
  against the **shared** `identity/sanctorale.ndjson`. So an edition can re-grade / re-date / re-colour a feast
  but **cannot re-title it** at this layer — which keeps the clean-room title-provenance rule intact (a 1954
  block adds no name, so it cites a reference source, never transcribes a title from one).
- **`rank` is DERIVED from `legacyRank` (built).** A legacy edition block authors only the grade; the generator
  (`DEFAULT_RANK_BY_LEGACY` in `transform.mjs`) derives the numeric `RankClass`, cited to the grade's own source,
  with an explicit self-cited `rankOverride` only where the 1960 revision re-graded a feast off the default. So
  `rank` and `legacyRank` can no longer silently disagree — a typo like `duplex-ii-classis` + a wrong numeric is
  impossible because the numeric is not authored. A sanctoral block using an unmapped grade (`dominica-*`,
  `feria-maior`) or a `rankOverride` missing its cite fails the build closed. Generator unit tests
  (`tools/generator/test/`, the new `npm test` gate in CI) cover the derivation + every fail-closed path.
- **Edition-diff report** (the epic AC) is not emitted yet — the "diff" is an authoring convention. When it lands
  it should flag diff-blocks byte-identical to the base realization (pure duplication, the thing most likely to rot).
- **MANIFEST caveat.** The per-edition `.ndjson` are byte-isolated by construction, but `MANIFEST.json`'s global
  `sources[].uses` counts are cross-edition — a future 1954/1955 fact citing a shared source (`rg-1960` / `mr-1920`)
  shifts those counts (never the 1962 data bytes; `verify` stays green).
- The sanctoral `legacyRank` enum also lists temporal grades (`dominica-maior`, `feria-maior`) — legal-but-unused
  in the sanctoral shape; scope them when the temporal-edition attributes land (#61).

## Seam 4 — the octave subsystem (SAFER SPLIT — #65)

The 5-agent code mapping (2026-07-04) showed "octaves" are **two different mechanisms**, so a single generic
generator is the wrong shape:

- **Temporal octaves** — Christmas, Easter, Pentecost (and, for 1954, Epiphany, Corpus Christi, Ascension,
  Sacred Heart) are **movable**, minted at runtime by the temporal fillers (`ChristmasCycle`, `Eastertide`)
  as they walk the season blocks, with real quirks (the Pentecost **Ember Days** sit *inside* the octave with
  their own ids; Easter's "Sabbato in Albis"). 1962 already produces its three this way.
- **Sanctoral octaves** — Assumption, All Saints, Peter & Paul, Immaculate Conception, John Baptist, Joseph
  (+ the simple octaves Stephen/John-Ev/Innocents/Lawrence/Nativity-BVM) are **fixed-date, feast-anchored**
  windows that behave exactly like **vigils** — which in this corpus are pure placement DATA, no engine.

**Decision (user-approved 2026-07-04): the safer split. Built — #65.** Do NOT refactor 1962's temporal-octave
fillers — the golden fixture is protected *by construction*, not merely gated. Sanctoral octaves are
**materialised DATA**: a per-edition `facts/octaves.yaml` (each entry `{bearingFeast, class: common|simple,
genitive, cites:{class,name}, octaveDay?}`) that the NODE generator's `transformOctaves` expands into full
identity + attributes + placement records — the six days within (dies secunda..septima) for a common octave,
and the octave day (dies octava, +7) for both classes — each linked back with `octaveOf` (mirroring `vigilOf`).
Runtime reads them via `CorpusSanctoralData` with the same trailing-optional `octaveOf` plumbing vigils use; no
PHP runtime `OctaveGenerator` decorator, and the 1960 edition declares **no** octaves. As-built notes:

- **Derived, not authored twice.** The octave's **date** (day-offset) and **colour** are derived from the
  bearing feast's own edition block; its **numeric rank** is derived from the office-grade exactly as a feast's
  is (PR #448) — common: days-within `semiduplex`→III, octave day `duplex-maius`→III; simple: octave day
  `simplex`→IV, no days-within. So `octaves.yaml` authors only class + the Latin genitive + two cites.
- **No new corpus schema.** The identity schema already reserved the `within-octave` / `octave-day` kinds; the
  only schema change is a `octaveOf` field on `placement.sanctorale` (mirroring `vigilOf`). `octaves.yaml`
  itself is validated in-code, fail-closed (unknown class, absent bearing feast, missing genitive/cites).
- **Shared identity = the cross-edition UNION.** An octave day is edition-invariant identity but exists only in
  editions that keep octaves; its identity is merged (deduped) into the shared `identity/sanctorale.ndjson`,
  which thus becomes the union of observances across editions — each edition selects what it observes via its
  own placement. The 1960 edition places none, so its resolution (golden fixture) and its edition dir stay
  byte-identical; the whole octave delta lands in `editions/roman-divino-afflatu/` + the shared identity.
- **Emit-what-is-confirmed, defer-what-is-not (100%-accuracy discipline).** The octave-facts research pass
  (adversarially verified) authored: **John Baptist** (Jun 24) + **Peter & Paul** (Jun 29) as full common
  octaves, **Lawrence** (Aug 10) as a simple octave (its Aug 17 octave day survives as a commemoration —
  confirmed), and the **Assumption** (Aug 15) common octave's six days within (Aug 16–21). **St Joseph** (Mar
  19) has **no** octave — the octave belonged to the *moveable* Solemnity of St Joseph, abolished 1955.
  **Deferred** (to #64 + #67, flagged HOLD by the research — the office is displaced but survival as a
  commemoration is unconfirmed): the **Assumption octave day** (Aug 22, occupied by the Immaculate Heart of the
  BVM, duplex II, 1944 — expressed as `octaveDay: false`) and the whole **Nativity-BVM simple octave** (its
  Sep 15 octave day replaced by the Seven Sorrows, duplex II).

- **Rules (next — #67).** `Rubrics1954Precedence` places the materialised within-octave/octave-day observances
  on the pre-1955 occurrence tiers, deciding the per-class behaviours (common omitted under a I/II-class feast
  but commemorated; simple keeps only the octave day; octave-vs-octave overlap) and resolving the deferred
  octave days above once #64 adds their displacing feasts (Immaculate Heart, Seven Sorrows).
- **Guardrails.** The 1583–2200 golden fixture stays byte-identical (1962 untouched — proven) **and** named
  octave tests pin the 1954 data outcomes (`OctaveEditionTest`: Jul 1 = Octave Day of St John Baptist, greater
  double, white, `octaveOf` the feast; Aug 18 = 4th day within the Octave of the Assumption, semidouble; Aug 17
  = simple Octave Day of St Lawrence with no days within; the deferred days absent; 1960 places no octave).

## Seam 5 — pre-1955 vigils (#66)

Vigil infrastructure is complete (identity → `placement.vigilOf` → `SanctoralCalendar` day-before placement
→ contract `vigilOf`). Reintroducing pre-1955 vigils is therefore **data** in the 1954 edition dir (identity
records for any new vigils + placement records with `vigilOf` + attribute rank/colour), plus a
`Rubrics1954Precedence` vigil tier and the "commemorated when impeded, suppressed on a Sunday/higher feast"
rule. 1955 (#70) omits the vigils *Cum nostra* suppressed simply by not emitting them in the 1955 dir.

## Seam 6 — pre-1955 & 1955 precedence rules (#67, #70)

`Rubrics1954Precedence` and `Rubrics1955Precedence` implement `PrecedenceRules` beside `Rubrics1962Precedence`,
each reading its edition's `precedence.tiers.ndjson` + `precedence.rules.ndjson`:

- **1954** — the pre-1955 Tabella occurrentiae/concurrentiae: the double/semidouble/simple order, octave and
  vigil tiers, the older Sunday-resumption, and the more generous transfer (a double could be transferred,
  not only a first-class feast) and commemoration rules.
- **1955** — the *Cum nostra hac aetate* reductions on the same base: three octaves, the reduced vigil set,
  fewer commemorations, first-Vespers simplifications.

## Seam 7 — stamp the active rubric system into the contract (#74)

`Provenance.edition` already carries the edition URN and the contract already serialises it; the only work is
ensuring it is set from the selected `RubricSystem`, and advertising the available systems (+ validity
windows) on the Api's `/meta`. No contract-shape change — the frozen 1.0.0 contract already reserved this.

## Seam 8 — regression safety & the multi-system matrix (#72, #73)

- The 1962 **golden fixture (1583–2200)** must stay byte-identical after every seam change — the proof the
  abstraction did not regress 1962.
- #73 adds a **rubric-system matrix** to the CI validation job: the harness runs each built system against
  its **≥2 pinned oracles** (the #59 AC), and the golden fixture runs per system once each is built.

## Data authoring — editions as diffs (#64, #69)

Per the epic ACs, 1954 and 1955 are **authored as diffs from the 1962 facts** (only changed records) in the
generator source (`tools/generator/facts/editions/…`), and the generator **materialises** each edition into
its own full `data/corpus/editions/<edition>/` tree + emits an **edition-diff report** (what changed vs
1962). Runtime still loads one edition directory (no runtime overlay) — the "diff" is a source/authoring and
comparison convenience, consistent with `corpus-schema.md`. 1955 is authored as a diff from 1954 where that
is smaller (it is largely "1954 minus the *Cum nostra* suppressions").

## Edition identifiers & validity windows

| Edition | URN | Corpus dir | Validity window | Supersedes → superseded-by |
|---------|-----|-----------|-----------------|----------------------------|
| Divino Afflatu (1954) | `roman:divino-afflatu` | `roman-divino-afflatu` | 1914(?)–1955 | ← Tridentine / → 1955 |
| Interim (1955) | `roman:rubricae-1955` | `roman-rubricae-1955` | 1956–1960 | ← 1954 / → 1960 |
| Rubricae 1960 (1962) | `roman:rubricae-1960` | `roman-rubricae-1960` | 1961–present (trad. use) | ← 1955 / → (living NO on its own axis) |

_(URN/window specifics confirmed against research; the 1954 window start depends on which typical-edition
snapshot we pin — see oracles.)_

---

## LITURGICAL CONTENT (from the fact-research pass — primary texts: *Cum nostra hac aetate*, AAS 47 [1955] 218–224; Codex Rubricarum, AAS 52 [1960] 593–740)

### Two corrections baked in
- **1955 abolished the Semiduplex grade** (Cum nostra Title II n.1) — semidouble feasts → Simplex, simple
  feasts → bare commemoration. So the grade set differs across all three editions (below). The I–IV *class*
  renaming is a **1960-only** event.
- **The Pentecost octave survives in 1962** (Rubricae n.66; season def n.76c) — a 1st-class octave. The
  engine already carries Easter/Pentecost octaves in the 1962 tier table (`precedence-model.md` line 10/48),
  so the unified octave subsystem must **preserve all three 1962 octaves**, not drop Pentecost.

### Rank order & mapping (`LegacyRank` → `RankClass`)
| Old grade (→1955) | present in 1954 | present in 1955 | 1962 class (default, then diff vs actual calendar) |
|---|---|---|---|
| Duplex I classis | ✓ | ✓ | **I** |
| Duplex II classis | ✓ | ✓ | **II** |
| Duplex maius | ✓ | ✓ | **III** (most) |
| Duplex (minus) | ✓ | ✓ | **III** |
| **Semiduplex** | ✓ | **abolished** | (n/a — gone by 1955) |
| Simplex | ✓ | ✓ | **commemoration** (or III if retained) |
| _(bare Commemoratio)_ | ✓ | ✓ (simples demoted here) | commemoration |

The map is **deliberately not 1:1** (feasts were re-graded/re-dated in the 1960 calendar revision) — treat it
as a default and diff against the actual per-edition calendar entries. `LegacyRank` holds the original token;
`RankClass` is the normalised tier sort key.

### Octave inventory (per edition)
- **1955 & 1962 — three octaves:** Christmas, Easter, Pentecost.
  - 1962 classes (n.65–67): **Easter octave = I**, **Pentecost octave = I**, **Christmas octave = II** (its
    octave day, 1 Jan, = I). Days-within inherit the octave class.
- **1954 — the full pre-1955 system: 18 octaves in 5 classes** (source: 1913 CE "Octave"; GRC-1954;
  restorethe54; to be regression-checked vs the St. Lawrence Press pre-1955 Ordo):
  - **Privileged 1st order (2):** Easter, Pentecost — no feast admitted within; no commemoration until
    Vespers of Tuesday.
  - **Privileged 2nd order (2):** Epiphany, Corpus Christi — days-within = semidouble (yield only to a
    Double I class), octave day = Duplex maius; **always commemorated**.
  - **Privileged 3rd order (3):** Christmas, Ascension, Sacred Heart — any feast above Simplex is celebrated
    within, but the octave is **always commemorated** (never dropped).
  - **Common (6):** Immaculate Conception, St Joseph, John the Baptist, Sts Peter & Paul, **Assumption**,
    All Saints (+ local Dedication/Titular/principal Patron). Octave commemorated but **omitted under a
    Double I/II-class feast**.
  - **Simple (5):** St Stephen, St John Ev., Holy Innocents, St Lawrence, Nativity BVM (+ local secondary
    patrons). **Only the octave day** is kept (as a Simple); no days-within.
  - Data note: the octave **class** drives days-within rank, octave-day rank, and the occurrence rule
    (drop-vs-always-commemorate); `octaves.ndjson` carries `{feastId, class, days:8}` and the
    `Rubrics1954Precedence` encodes the five per-class behaviours. (Assumption is **common**, not 3rd-order —
    a frequent error, corrected.)

### Vigil inventory (per edition)
- **1955 — 7 vigils** (Cum nostra nn.8–9): *privileged* Christmas, Pentecost; *common* Ascension,
  Assumption, John the Baptist, SS Peter & Paul, St Lawrence. All others (Epiphany, Immaculate Conception,
  All Saints, the Apostles' vigils except Peter & Paul) **suppressed**.
- **1962 — same 7, reclassified** (Rubricae nn.30–32): **I class** Christmas, Pentecost; **II class**
  Ascension, Assumption, John Baptist, Peter & Paul; **III class** Lawrence. No net cut 1955→1962 — only
  Lawrence demoted. A II/III-class vigil is **omitted** on a Sunday or I-class feast (n.33).
- **1954 — 17 vigils** (source: 1913 CE "Eve of a Feast", verbatim; verify vs the pre-1955 Ordo):
  - **Major/privileged (proper semidouble office):** Christmas (Dec 24 — kept as a Double from Lauds,
    **displaces the 4th Sunday of Advent**), Pentecost, **Epiphany** (Jan 5 — festal/white; suppressed 1955).
  - **Common/minor (ferial office, violet Mass):** Ascension, John the Baptist (Jun 23), Sts Peter & Paul
    (Jun 28), Lawrence (Aug 9), Assumption (Aug 14), All Saints (Oct 31), Immaculate Conception (Dec 7),
    and the **eight Apostles' vigils** — Andrew (Nov 29), Thomas (Dec 20), James the Greater (Jul 24),
    Bartholomew (Aug 23), Matthew (Sep 20), Simon & Jude (Oct 27), Matthias (Feb 23 / Feb 24 in leap years).
  - **No vigil:** Philip & James (May 1, Paschaltide) and John the Evangelist (Dec 27). A common vigil on a
    Sunday is **anticipated to the preceding Saturday** (pre-1955 rule); impeded by a double/semidouble it is
    commemorated, by a Double I-class dropped.
  - The 1954→1955 cut removes 10 (Epiphany, Immaculate Conception, All Saints, the seven non-Peter&Paul
    Apostles' vigils); 1962 keeps the 1955 seven, reclassified.

### The 1954 → 1955 → 1962 delta table (firm from primary text for the 1955/1962 columns)
| Dimension | 1954 (pre-1955) | 1955 (Cum nostra, eff. 1 Jan 1956) | 1962 (Codex Rubricarum) |
|---|---|---|---|
| Rank scheme | Dx I/II cl., Dx maius, Dx, **Semiduplex**, Simplex | **Semiduplex abolished**; Dx I/II cl., Dx maius, Dx, Simplex, Commem. | **I/II/III/IV class** (n.91 annuls all prior) |
| Octaves | ~15 (privileged/common/simple) | **3** (Christmas, Easter, Pentecost) | **3** (Christmas II, Easter I, **Pentecost I**) |
| Vigils | ~16 (three classes) | **7** (2 priv. + 5 common) | **7** (same set; Lawrence→III) |
| Max commem. | orations ≤3; multiple common | **0** on I-cl/privileged & sung; **1** on II-cl & other Sundays; **≤2** else | privileged-only on I-cl/sung; **1** on II-cl; **≤2** on III/IV (same shape as 1955) |
| Sunday | semidouble Sundays raised (Pius X) | Advent/Lent/→Low + Pentecost = **Dx I cl.**; impeded Sunday not resumed; Lord's feast displaces *per annum* Sunday | I-cl Sundays (Advent/Lent/Passiontide/Easter/Low/Pentecost) vs II-cl (rest) |
| Feria | greater/greater-non-priv./simplex | semidouble→simple demotions; Lenten feria may be chosen over non-I/II feast | Lent/Passiontide feria = **III** (outranks III-cl feast); Advent→16 Dec = III; rest = IV |
| Suffrages | single *A cunctis* | **abolished** | abolished |
| Preces | dominical/ferial at hours | **only** Lauds/Vespers Wed/Fri of Advent-Lent-Passiontide + Ember Wed/Fri/Sat | same restricted set |
| Athanasian Creed | many Sundays | **Trinity Sunday only** | Trinity Sunday only |
| First Vespers | doubles & semidoubles | I/II-class feasts + Sundays only | I-class feasts + Sundays (+ II-cl Lord's feasts displacing a II-cl Sunday) |

_(v0.1.0 is calendar-level — the Office-only rows (suffrages, preces, Athanasian Creed, first-Vespers detail)
are recorded for fidelity + the future Office layer, but the engine's v0.3 job is the **calendar** rows: rank,
octaves, vigils, commemorations, Sunday/feria precedence.)_

### Per-edition `PrecedenceRules` behaviour (what each impl encodes)
Firm from the primary texts; the three impls differ chiefly here:
- **`Rubrics1954Precedence`** — the pre-1955 Tabella occurrentiae/concurrentiae: the double/semidouble/simple
  order; **full octave/vigil inventory**; the more generous **transfer** (an impeded *double* could be
  transferred, not only a first-class feast); **multiple commemorations** (orations ≤3, several commemorations
  common); **full concurrence** (compare a day's 2nd Vespers against the next day's 1st Vespers by *dignitas*,
  with Vespers "a capitulo" splitting); first Vespers for doubles **and** semidoubles.
- **`Rubrics1955Precedence`** — same base minus the *Cum nostra* reductions: **Semiduplex suppressed**;
  octaves → 3, vigils → 7; **commemoration caps 0/1/2** by day-type (the shape 1962 keeps); first Vespers
  restricted to **I/II-class feasts + Sundays**, which collapses most concurrence; impeded Sunday **not
  resumed**; a feast of the Lord on a *per annum* Sunday **displaces** it.
- **`Rubrics1962Precedence`** (built) — the four-class n.91 table; occurrence n.93 (translation reserved to
  higher-class feasts, nn.95–96); concurrence nn.103–105 (higher Vespers said whole, no splitting);
  commemorations nn.106–114 (privileged-only on I-class, one on II-class, ≤2 on III/IV, excess omitted n.114).

The **octave/vigil inventory is identical between 1955 and 1962** (1955 did the cutting; 1960 only re-ranked),
so `roman-rubricae-1955` and `roman-rubricae-1960` share the same octaves/vigils data and differ in the
precedence tiers + rank scheme — a small, well-scoped diff.

### Validation oracles (≥2 per edition — #59 AC / #71)
- **1954:** (1) **St. Lawrence Press, *Ordo Recitandi* (pre-1955 rite)** — the native annual Ordo, still
  printed, with a day-by-day companion blog (`ordorecitandi.blogspot.com`); the gold oracle. (2) Wikipedia
  "General Roman Calendar of 1954" (secondary, spot-verify). (3) a period diocesan/order *Ordo* for 1954
  (WorldCat).
- **1955:** (1) a printed diocesan *Ordo Recitandi* for any year **1956–1960** (native to the interim
  rubrics). (2) the decree *Cum nostra hac aetate* (AAS 47) as the normative spec. (3) *divinumofficium.com*
  "Rubrics 1955" mode — **cross-implementation check only**, never a data source (clean-room).
- **1962** (baseline, already validated): Codex Rubricarum (AAS 52) + a current FSSP/ICKSP/SSPX printed Ordo.
