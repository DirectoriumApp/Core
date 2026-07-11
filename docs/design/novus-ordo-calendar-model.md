# The Novus Ordo calendar model — Core v1.1 (#256, #257, #258, #366, #260)

_Status: active. How the engine resolves the **Ordinary-Form (Novus Ordo) General Roman
Calendar** as a new **edition** on the existing edition axis — its rank scale, its Ordinary-Time
temporal cycle, its Table of Liturgical Days precedence, and the living-calendar snapshot/decree
governance — **without** touching the frozen 1962 golden or the frozen 1.0 output contract beyond
two reserved, additive extensions. Builds on the edition axis
([`edition-governance.md`](edition-governance.md)), the rubric-system model
([`rubric-system-model.md`](rubric-system-model.md)), the open season vocabulary
([`season-vocabulary.md`](season-vocabulary.md)), the born-cited corpus
([`corpus-schema.md`](corpus-schema.md)), and the text-licensing rule
([`text-licensing-model.md`](text-licensing-model.md)). Signed off 2026-07-10._

## Scope — calendar-level, CC0 facts only

v1.1 resolves the **calendar day**: the celebrated office, its **rank** (solemnity / feast /
memorial / optional memorial), **colour**, **season**, Ordinary-Time **week number**, the
movable feasts, and occurrence precedence. Everything it emits — dates, ranks, colours, seasons,
the Table of Liturgical Days — is an **uncopyrightable liturgical fact** and ships **CC0**
([`text-licensing-model.md`](text-licensing-model.md)). It does **not** emit the Mass propers,
the Office, or the lectionary; those are copyrighted post-1962 texts (LEV/ICEL) and belong to
later, licence-gated milestones. Clean-room: the calendar is authored from the promulgating norms
(the *Normae universales de Anno liturgico et de Calendario*, 1969; GIRM §346; the Missal's
*Table of Liturgical Days*; dated decrees by their Prot. number) and cited to them; the
validation oracles (§Validation) only **re-prove** it and are never a data source.

## The signed-off shape: a unioned peer edition

The Novus Ordo is authored as a **whole, first-class edition inside the existing corpus**,
sharing the cross-edition **identity union** with 1962/1954/1955 — not as a diff of 1962 (it has
its own sanctoral, ranks, dates, and temporal skeleton) and not as a separate corpus. The saint
that is `roman:sanctorale:augustinus` in 1962 (III class, 28 Aug) is the **same id** realized as
a memorial in the Novus Ordo, so the flagship v2.0 comparison tool aligns editions **by id**.
This preserves the 3-layer "identity stated once, realized per edition" model the whole platform
rests on ([`corpus-schema.md`](corpus-schema.md)). It costs four generator additions (§Corpus).

**The 1962 golden fixture (1583–2200) stays byte-identical throughout.** The Novus Ordo adds a
new edition directory and merges its observance identities into the shared union; the 1962
edition places none of the NO-only observances, so its resolution and its corpus bytes are
unmoved — exactly the guarantee the 1954/1955 build honoured.

## The edition axis — snapshots and the isBuilt arc

The Novus Ordo is a **living calendar**, so it is not one edition but a stream of *editio typica*
**snapshots** plus dated **decrees** ([`edition-governance.md`](edition-governance.md)). The
snapshot is named in the edition URN:

| Snapshot | URN | Corpus dir | Validity | v1.1 |
| --- | --- | --- | --- | --- |
| editio typica 1969 | `roman:novus-ordo-1969` | `roman-novus-ordo-1969` | 1970–1974 | **reserved** (declared, `isBuilt=false`) |
| editio typica altera 1975 | `roman:novus-ordo-1975` | `roman-novus-ordo-1975` | 1975–2001 | **reserved** (declared, `isBuilt=false`) |
| editio typica tertia 2002 (+ 2008) | `roman:novus-ordo-2002` | `roman-novus-ordo-2002` | 2002–present | **BUILT** |

We build **`roman:novus-ordo-2002`** — the *current* general calendar (2002/2008 with promulgated
decrees folded to date). It is what every consumer (Api, Ordo, Site) needs now, and all four
validation oracles compute the current calendar. The 1969/1975 snapshots are **reserved** as
declared-but-unbuilt URNs; they later become a cheap "replay the decree ledger as of 1970 / 1975"
exercise, not a hand-authored corpus, so naming their slots now keeps their eventual insertion
additive. Aliases: `novus-ordo`, `ordinary-form`, `2002` → the 2002 snapshot.

Following the 1954/1955 precedent, the NO edition is declared `isBuilt=false` during the build
(reachable only through `DayResolver::forEdition()` for testing, **refused at the public
`CalendarCatalog` boundary** with the "declared but not yet built" message) and its flag flips to
`true` at the epic wrap, once the calendar is complete and oracle-validated.

## The rank scale and the Table of Liturgical Days (#257)

The NO names three grades — **solemnity, feast, memorial** — with memorials subdivided into
**obligatory** and **optional** (Norms n. 10, n. 14): four working ranks. These are the NO's own
vocabulary, so — exactly as 1954 preserves its `legacyRank` grade token beside the normalized
`RankClass` — the NO carries a **`novusRank` token** (`solemnity` / `feast` / `memorial` /
`optional-memorial`) whose numeric `RankClass` is **derived** (`solemnity`→1, `feast`→2,
`memorial`→3, `optional-memorial`→4) and cited to the same source as the grade. The 1–4 range
already fits; only the token vocabulary and its derivation map are new. The token is what the
display and the tier logic read; the numeric class is the normalized sort key.

Precedence is the **Table of Liturgical Days** (Norms n. 59) — a flat, four-band list resolved
strictly by line number (lower wins), authored **whole** as the edition's
`precedence-tiers.ndjson` (the same "author the table whole, not as a diff" pattern the pre-1955
`precedence.yaml` already uses). The load-bearing readings the engine encodes:

- **Group I** (line 2): Triduum; Christmas/Epiphany/Ascension/Pentecost; **Sundays of Advent,
  Lent, Easter**; Ash Wednesday; Holy Week Mon–Thu; Easter-octave days — displaced by **nothing**
  below this line; a conflicting solemnity is **transferred**.
- **Line 3**: Solemnities (of the Lord, the BVM, General-Calendar saints) + **All Souls (2 Nov)** —
  these **outrank** an Ordinary-Time / Christmas-Time Sunday (line 6).
- **Line 5**: **Feasts of the Lord** (Baptism, Holy Family, Presentation 2 Feb, Transfiguration
  6 Aug, Exaltation of the Cross 14 Sep, Dedication of the Lateran 9 Nov) — the **only** "feasts"
  that outrank a Sunday (of OT / Christmas Time).
- **Line 7**: Feasts of the BVM and the saints — **omitted** in a year they fall on any Sunday.
- **Lines 10–13**: obligatory memorials; optional memorials (electable even on the privileged
  weekdays of line 9); OT weekdays.

## The temporal cycle — Ordinary Time (#258)

The NO seasons are **`advent` · `christmastide` · `ordinary-time` · `lent` · `eastertide`** (five;
registered as the NO subset in [`season-vocabulary.md`](season-vocabulary.md); `ordinary-time` is a
new shared token). There is **no** Septuagesima, **no** distinct Passiontide, **no** Time-after-
Epiphany or Time-after-Pentecost, **no** Ember/Rogation days by default, and **only two** octaves
(Christmas, Easter). The bare tokens `advent`/`lent`/`eastertide` are **shared** with the
traditional editions so the comparison diff aligns seasons by token; `ordinary-time` is the one
genuine addition.

**Ordinary Time is two disjoint blocks** with a single 1–34 week series engineered so the last
Sunday before Advent is always the 34th (Christ the King):

- **Block I** begins the Monday after the **Baptism of the Lord** as OT Week 1 (there is no "First
  Sunday in OT" — the Baptism occupies that Sunday; the next Sunday is the *Second Sunday in OT*)
  and runs through the Tuesday before Ash Wednesday.
- **Block II** resumes the Monday after Pentecost and runs to First Vespers of Advent I, closing
  with **Christ the King** on the 34th Sunday.
- **Resume number = 35 − W**, where **W = the number of Sundays in Block II** (Pentecost-Monday to
  Advent I). In a 33-week year exactly one week is **omitted** (the week that would have followed
  Block I); a genuine 34-week year is continuous. The count is done **backward from 34** — the
  same backward-count technique the traditional `TimeAfterPentecost` already uses for its resumed
  tail, so it is a known shape, not new machinery.

Movable feasts (Easter offset `E`, or civil-Sunday anchor):

| Feast | Rank | Rule |
| --- | --- | --- |
| Baptism of the Lord | Feast of the Lord | Sunday after 6 Jan (Monday after a Sunday-transferred Epiphany falling 7–8 Jan) |
| Holy Family | Feast of the Lord | Sunday in the Christmas octave, else 30 Dec |
| Ash Wednesday | (privileged weekday) | E − 46 |
| Trinity Sunday | Solemnity | E + 56 |
| Corpus Christi | Solemnity | E + 60 (Thu) or E + 63 (Sun) — conference toggle |
| Sacred Heart | Solemnity | E + 68 |
| Immaculate Heart | (obligatory) Memorial | E + 69 (Saturday after the Sacred Heart) |
| Christ the King | Solemnity | last Sunday of Ordinary Time (34th) |

Ascension is E + 39 (Thu) or transferred to the 7th Sunday of Easter (E + 42) — a conference
toggle. Because the block structure differs from the traditional year, the Novus Ordo supplies a
**new ordered set of fillers** (`OrdinaryTime` owning both blocks, plus NO Christmas-Time / Lent /
Holy Week / Easter-Time fillers without the pre-conciliar apparatus), and the **filler list itself
becomes edition-selected** in `DayResolver::resolveYear` (a `TemporalCycle` strategy keyed off the
`RubricSystem`) — the resolver is already precedence-agnostic via `PrecedenceRules`; this makes it
temporal-cycle-agnostic the same way. The Easter computus, `PaschalSkeleton`, `TemporalCalendar`,
`TemporalAttributes`/`TemporalArchetype`, and `TemporalObservance` are reused unchanged; the NO
supplies its own per-edition **temporal skeleton** (offsets + block→season map).

### Implementation sequencing (v1.1 build)

The Ordinary-Time engine and the edition-selected cycle seam land first, on their own, as the
`#258` slice: the `OrdinaryTime` filler (both blocks, the 34-week backward count, minted from
`ot-sunday`/`ot-feria` archetypes), the `TemporalCycle` / `YearTemporalCycle` strategy with
`TraditionalTemporalCycle` (byte-identical to the historic filler list) and `NovusOrdoTemporalCycle`,
the additive `AnchorFamily::ORDINARY_TIME`, and `TemporalCalendar::addDays` made signed. Because the
Ordinary-Time date arithmetic is pure Easter/civil computus, it **reuses the shared `PaschalSkeleton`
unchanged** — the NO needs no distinct Easter anchors — so no `PaschalSkeleton` global is touched and
the 1962/1954/1955 corpus stays byte-identical. The `#258` filler tests run against a minimal
temporal fixture corpus.

Two pieces the fuller design lists under the temporal cycle move to the **general-calendar corpus
slice (`#108`)**, where they belong with the cited data and confront the born-cited/public-domain
naming gate once, holistically: (1) the **per-edition `temporal-skeleton.yaml`** generator addition
and its block→season map — deferred because Ordinary Time expresses its seasons in the filler and
needs no new offsets; and (2) the **remaining NO season fillers** (Advent / Christmas Time / Lent /
Holy Week / Easter Time) with their cited archetypes. The `NovusOrdoTemporalCycle` filler set and the
Triduum source are completed there. The edition remains `isBuilt=false` throughout, so nothing is
publicly resolvable until `#108`/`#260` land.

## Precedence — simpler, not harder (#257)

`NovusOrdoPrecedence implements PrecedenceRules` beside the three traditional impls, selected by a
new `case` in `DayResolver::rulesFor`. The NO precedence world is **flatter** than the traditional
one:

- **No commemorations.** `commemorationLimit` / `commemorationClassLimit` → **0**,
  `isPrivilegedCommemoration` → false. `CommemorationSelector` already short-circuits a 0 limit to
  an empty list, so the contract's `commemoration` is `[]` and `commemorationLimit` is `0` — both
  correct with no selector change. When two celebrations occur, the higher **wins**; the loser is
  **transferred** (an impeded Solemnity, reusing `TransferLedger` + `forcedTransferDate` — e.g. the
  Annunciation out of Holy Week / the Easter octave) or **omitted** (everything else). `tierOf`
  never returns `commemorate`.
- **All Souls stays on a Sunday.** `officeOfTheDeadYieldsToSunday()` → **false** (the interface
  already anticipates this) — 2 Nov at line 3 displaces an OT Sunday.
- **`anticipatesSundayVigils()`** → false.
- **Concurrence is a near-no-op.** The NO Office gives First Vespers only to Sundays and
  solemnities (and Feasts of the Lord on a Sunday); there is no traditional Vespers-concurrence
  contest. `concurrenceOutcome` returns only `fullOfPreceding` / `fullOfFollowing` by tier. To
  avoid stamping a meaningless second-Vespers result on ordinary NO weekdays, one **additive**
  edition boolean `observesVespersConcurrence(): bool` is added to `PrecedenceRules` (false for NO,
  true for the traditional editions) gating `DayResolver::withConcurrence` — the same additive-
  boolean pattern used twice already, and an internal (`@internal`) interface, so no public break.

## The corpus — four generator additions (#108)

The generator authors 1954/1955 as diffs on one shared sanctoral file; authoring the NO as a
**whole peer edition inside the same corpus** (preserving the identity union) adds four things,
each following an existing template:

1. **Per-edition sanctoral source files** — teach `build()` to load
   `facts/editions/<dir>/sanctorale.yaml`, fan its entries through a variant of
   `transformSanctoraleEdition`, and **merge their identities into the shared union** exactly as
   octave and temporal-archetype identities already merge (the dedup-or-throw pattern is the
   template; this is the load-bearing addition).
2. **A NO rank vocabulary** — a `novusRank` field + enum in the sanctoral schema and a
   `deriveNovusRank` map in `transform.mjs` (a sibling of `DEFAULT_RANK_BY_LEGACY`), so the NO
   grade and its derived numeric class cannot silently disagree and the pre-1960 `legacyRank` bag
   is not overloaded.
3. **A per-edition temporal skeleton** — allow `facts/editions/<dir>/temporal-skeleton.yaml`
   (Easter offsets + block→season) so the NO can drop the Septuagesima/Ember anchors and re-map
   the green weeks to `ordinary-time`, rather than inheriting the single shared 1962 skeleton.
4. **New registered sources** — the NO typical editions (`kind: text` where a Latin title is
   transcribed from a public-domain source; ancient titles like *Nativitas Domini* are PD by age),
   the *Normae universales* (`kind: reference` for rank/date facts), each sanctoral decree (by Prot.
   number), and the validation oracles (`kind: oracle`).

Born-cited discipline is unchanged: every NO datum carries a citation or the build fails closed;
a transcribed title must cite a public-domain `text` source, while facts (rank/colour/date) may
cite any registered source. The **penitential discipline** for the NO is the 1983 Code /
*Paenitemini* (`cic-1983`) — a small, citable discipline (Ash Wednesday + Good Friday fast &
abstinence; Fridays of Lent abstinence; Fridays of the year abstinence-or-substitution) authored
as a new discipline record so NO fasting is correct rather than reusing the 1917 Code.

## Edition governance — the decree ledger (#366)

The base `roman:novus-ordo-2002` corpus carries the roster as of the 2002/2008 editio typica.
Post-2002 general-calendar **decrees** (St Mary Magdalene raised to a **Feast**, 22 Jul, Prot.
257/16, 3 Jun 2016; the **BVM Mother of the Church** obligatory memorial, Monday after Pentecost,
Prot. 10/18, 2018; St Faustina 5 Oct 2020; the 2021 Doctors — Gregory of Narek / John of Ávila /
Hildegard; etc.) are modelled as **dated overlay files** under
`editions/roman-novus-ordo-2002/decrees/<effective-date>-<slug>.yaml`, reusing the particular-
calendar overlay engine. Each carries an **effective date** and applies **on or after** it, so
"the calendar as of date D" = base + decrees effective ≤ D reproducibly. This satisfies the #366
ACs (snapshots resolve independently and are diffable; a dated decree applies on/after its
effective date only; the snapshot/decree axis is reserved in the edition grammar); the decree
ledger is a **first-party temporal overlay** on the OF base, distinct from the society/national
overlays.

**Conference variation stays out of the universal core.** The three Sunday-transfer choices
(**Ascension**, **Epiphany**, **Corpus Christi** → Sunday) are **resolve-time toggles** (the same
shape as the `Cum sanctissima` flag), defaulting to the universal Thursday / 6-Jan form. National
propers and elevations (USCCB Guadalupe-as-Feast, national patrons, *Jesus Christ Eternal High
Priest*) are **particular-calendar overlays** (`directorium:overlay:roman:usccb`, …), authored and
validated per conference — the exact analogue of the SSPX/FSSP/ICKSP overlays over universal 1962.
Both are documented hooks; their content is a later slice.

## The two additive contract bumps (1.0.2 → 1.1.0)

Both extensions were **reserved** in the Phase-A design wave, so both are additive-within-1.x — the
frozen 1.0.2 shape stays a strict subset and every existing consumer is unaffected:

1. **The `ordinary-time` season token** — a new open-vocabulary member, a minor bump by the
   season-vocabulary rule.
2. **Optional-memorial choice-days** — a NO Ordinary-Time weekday may carry **0..n optional
   memorials** the celebrant freely elects. v1.1 resolves the day **deterministically** (the
   `celebration` is the obligatory memorial if any, else the feria) and lists the electable
   options in an additive `optionalMemorials` slot (the shape Phase A reserved for exactly this —
   "multiple celebration entries + an additive optionality key, never new closed-enum members").
   A Saturday of Ordinary Time with no obligatory memorial additionally admits the optional
   memorial of the BVM.

`SHAPE_VERSION` moves `1.0.2 → 1.1.0`; `PublicApiSnapshotTest` / `ContractShapeTest` are updated to
the new additive shape, and the 1962 golden value digest (which never emits these) is unmoved.

## Validation — ≥2 independent oracles (#260)

Run-and-compare only; the corpus is authored independently and born-cited, and no oracle is ever
copied (clean-room stands regardless of licence):

1. **romcal** (JS, **MIT**) — headless `generateCalendar(year)` with the universal toggles off →
   diff against `roman:novus-ordo-2002` fixtures.
2. **LitCal** (PHP, **Apache-2.0**) — `GET /calendar?year_type=LITURGICAL` (decree-aware; also the
   oracle for national overlays).
3. **calendarium-romanum** (Ruby, LGPL/MIT) via the hosted `calapi.inadiutorium.cz` — a third-
   language, zero-install triangulation point.
4. **USCCB annual PDF** (human-authored) — the **correlation-breaker**: three code oracles all
   encode the same norms, so their agreement proves fidelity-to-the-norms, not source-independence;
   a human ordo catches a shared misreading. Its universal spine checks the base; its US propers
   check the (future) USCCB overlay.

The CI `validate` matrix gains a **`novus-ordo-2002` arm** wired to the code oracles, with pinned
agreement fixtures asserted without a network dependency (the pattern the Divinum Officium fixtures
use) and tracked discrepancies catalogued in `known-differences.ndjson`.

## Deferred / out of scope for v1.1

- Mass propers, the Office, the lectionary (copyrighted post-1962 texts — later milestones).
- The full ~180-entry optional-memorial roster is authored for calendar completeness, but its
  *texts* are not; unavailable text degrades per [`text-licensing-model.md`](text-licensing-model.md).
- National/diocesan overlays (USCCB etc.) — hook documented, content later.
- Historical-snapshot replay (resolving 1969/1975/an arbitrary past date via the decree ledger) —
  the mechanism lands here; the reserved snapshots build later.
- NO fasting beyond the minimal `cic-1983` discipline (the full modern penitential casuistry is a
  later fasting-milestone concern).
