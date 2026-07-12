# Known limitations

An honest register of what Directorium does **not** yet cover, or covers with lower confidence. Trust comes
from disclosing gaps, not hiding them. Items here are candidates for the roadmap and are surfaced (where
relevant) as `confidence` flags in the data and in the API's coverage report.

## Coverage

- **Editions:** four rubric systems are **built and publicly resolvable** — **1962 (Rubricae 1960)**,
  **1954 (Divino Afflatu)**, **1955 (Cum nostra / interim)** (#453), and the **Novus Ordo *editio typica
  tertia* 2002** (#256, oracle-validated in #260). The reserved 1969/1975 Novus-Ordo snapshots and a future
  Tridentine (1570/~1906) are declared/roadmapped but **not yet built**, so the `isBuilt()` public-boundary
  guard refuses a selector naming one until its data and rules land.
- **Pillars:** the **calendar** comes first; the **Missal** (Mass propers/ordinary) and **Breviary**
  (Divine Office) text layers are later milestones. The Office psalter is initially the 1962 scheme only;
  the pre-1911 and Divino Afflatu psalters are a later milestone.
- **Particular calendars:** four society/institute overlays are **built** over the universal calendar —
  **SSPX**, **FSSP**, **ICKSP**, and **Generic 1962** (the identity overlay). SSPX is cross-checked against
  its live published ordo feed; FSSP and ICKSP are **deliberately NON-EXHAUSTIVE, well-attested subsets** —
  each society's headline proper-patron elevations, cited to its own published calendar, **not** the complete
  promulgated *Proprium* (the full proper calendars await a pinnable society ordo, as SSPX has). Regional/
  diocesan calendars and religious-order propers remain roadmapped. The **Cum sanctissima** (2020) optional
  celebration of post-1960 saints (#85) is deferred to v2.3.0, where the post-1960-saints corpus it needs is
  built — until then the 1962 books have no eligible saint for the toggle to resolve.
- **Movable feasts of the Lord/Saints:** the sanctoral corpus (#41) covers the **fixed-date** universal
  calendar. Feasts tied to a Sunday rather than a civil date — the **Holy Name of Jesus** (Sunday between
  1 and 5 Jan, else 2 Jan), the **Holy Family** (Sunday after Epiphany), and **Christ the King** (last
  Sunday of October) — are not yet modelled; they await a movable-feast treatment in the temporal layer.
  The fixed feasts of the Lord that *are* octave days (the Circumcision on 1 Jan) are resolved temporally.

## Reckoning & edge cases

- **Year range:** Gregorian Easter (`Computus`) is defined from **1583** onward; earlier (Julian) reckoning
  is out of scope for now. The **whole-year resolver** (`DayResolver::resolveYear()`) has an effective floor
  one year higher — **1584** — because it reaches back for the trailing Christmas cycle that bleeds into
  January (`ChristmasCycle::forYear($year - 1)`), and resolving 1583 would need the 1582 cycle, below the
  Gregorian floor. The golden-fixture gate (#365) therefore freezes 1584–2200; extending the resolver to
  cover 1583 is a possible future refinement (tracked in #415). Each edition is only meaningful within its **historical
  validity window**; resolving an edition outside its window is anachronistic and will be flagged.
- **Leap-year bissextile:** traditional reckoning doubles 24 February in a leap year (24 Feb "*bis*"),
  shifting St Matthias to 25 Feb and related observances — handled explicitly and tested.
- **Novus Ordo Triduum season (#108):** the reformed season vocabulary has five tokens — advent,
  christmastide, ordinary-time, lent, eastertide — and no distinct *Sacred Triduum* token, so the three
  Triduum days (Holy Thursday, Good Friday, Holy Saturday) carry `season: lent` in the output contract.
  Liturgically the Triduum (Holy Thursday evening → Easter, the *culmen* of the year, Universal Norms
  nn. 18–19) is its own time, and Lent ends before the Mass of the Lord's Supper (n. 28), so `lent` is the
  least-wrong of the available tokens for those days — a deliberate modelling choice, not a defect. Adding a
  `triduum` token is a candidate refinement, a minor open-vocabulary bump
  (docs/design/season-vocabulary.md) deferred to the validation/contract work (#260); it would touch only
  those three days' reported season, never a date, colour, kind, or precedence.
- **Novus Ordo Lenten memorial reduction (#260):** the reformed sanctoral's optional memorials are now
  surfaced — the day resolves deterministically to the obligatory office (memorial or feria) and the
  electable options are listed in the additive `optionalMemorials` slot (`SHAPE_VERSION` 1.1.0). One residual
  nuance remains: on a **Lenten weekday** an *obligatory* memorial is reduced to a commemoration in the
  reformed rite (the ferial Mass with the saint's collect), but the engine currently **displaces** it rather
  than keeping it as a commemoration. The *celebration* (the Lenten feria) is right; only the reduced saint's
  disposition differs. The finer office-level `optionality` marker (how the arms of a choice relate) stays a
  reserved contract slot.
- **Novus Ordo movable memorials (#108 / #366):** the fixed-date reformed sanctoral is complete, and the
  **movable-memorial mechanism now exists** — #366's dated-decree engine (`src/Decree/`) places a movable
  memorial by its Easter offset, gated by date. It resolves the **BVM Mother of the Church** (Monday after
  Pentecost), a 2018 decree, from its effective year. **One movable memorial remains deferred:** the
  **Immaculate Heart of Mary** (the Saturday after the Sacred Heart), an obligatory memorial *original to the
  2002 typical edition* (not a later decree), so it belongs in the base edition rather than a decree; it will
  reuse the same Easter-offset placement the decree engine established. It does not change a fixed-date
  resolution.
- **Novus Ordo Mother-of-the-Church coincidence precedence (#366):** the movable memorial of the BVM Mother
  of the Church (Monday after Pentecost, Easter+50) is celebrated correctly on a free green weekday, but on
  the ~14 years in 1583–2200 where that Monday coincides with a **fixed obligatory memorial** (the first is
  1 June 2020, St Justin; also 5 June 2028, St Boniface; 26 May 2042/2053, St Philip Neri; etc.) two rank-3
  obligatory memorials share the same precedence tier and the resolver breaks the tie by its deterministic
  id order rather than by a liturgical rule. The correct handling is a precedence *ruling* not yet
  implemented — either the instituting decree's clause that **Mother of the Church takes precedence**, or the
  general reformed rule that two coincident obligatory memorials both become optional and the **feria** is
  celebrated; settling which governs (with sources) is the follow-up. None of the validation fixture years
  (2025/2026) collide, so the oracle cross-check is unaffected, and the result is deterministic and
  reproducible (the golden and reproducibility invariants hold) — only the precedence *rule* on those dates
  is unruled.
- **Novus Ordo Friday abstinence on a solemnity (#108):** the reformed discipline (`cic-1983`) lays
  abstinence on every Friday of the year, but the c.1251 lifting of that abstinence when a **solemnity**
  falls on the Friday is not yet modelled (the resolver's Friday rule does not consult the day's grade);
  nor is the c.1253 conference substitution of another penance outside Lent (a national overlay). Both are
  refinements of the universal rule the corpus encodes, never a change to a calendar date or office.
- **Fast & abstinence (holyday dispensation):** the `fasting` block (#248/#249) applies the 1917 Code's
  core rules — abstinence on Fridays, fast and abstinence on Ash Wednesday, the Fridays and Saturdays of
  Lent, the Ember days, and the vigils of Christmas/Pentecost/Assumption/All Saints, and the fast alone
  (no abstinence) on the other Lenten weekdays (cann. 1252). **Not yet modelled** is the c.1252 §4 dispensation
  that lifts fast/abstinence when a day of precept (holyday of obligation) falls outside Lent — so, e.g., a
  first-class feast of precept on an ordinary Friday still shows abstinence. The **September Ember days** are
  likewise absent because the temporal engine does not yet place them (deferred with the movable-feast work,
  #22), so no fast attaches to them; the Advent, Lenten, and Pentecost Embers are covered. Both are additive
  refinements — the discipline data and the emergent-per-edition model already accommodate them.
- **Ecclesiastical lunar age (Luna):** the moon's age in the `calendar.astronomical` block (#244) is the
  schematic computus moon anchored to each year's paschal lunation, so **Luna 14 falls on the ecclesiastical
  paschal full moon exactly, every year**, and the age is continuous with proper hollow/full lunations
  through the whole liturgical year. What is **not** yet reconciled is the last lunation across the
  **civil-year boundary** — the embolismic (13th) month and the once-in-nineteen-years *saltus lunae* that
  the full Metonic lunar calendar carries — so the day-by-day `Luna` in late December / early January may
  differ by a day from the martyrology's tabular value. A full Metonic reconstruction is a possible future
  refinement; the year-level cyclic numbers (Golden Number, Epact, etc.) are exact.
- **Pre-1955 moveable feasts (Divino Afflatu 1954 / Cum nostra 1955, #453):** the two moveable feasts the
  1962 rubrics dropped — the Passiontide **Seven Sorrows** (Easter−9) and the moveable **Solemnity of St
  Joseph** (Easter+17) with its common octave (1954 only) — are modelled with a few deliberate simplifications,
  each to be pinned against a full-year Divino-Afflatu oracle sweep before the fixture is frozen:
  - **Octave day under a Double II:** the Solemnity's octave *day* (Easter+24, a greater double) is modelled
    as **commemorated, not omitted** when it yields to a Double of the II class (e.g. Ss Philip & James when
    1 May = Easter+24, as in 1996). The omit rule of a *common* octave is applied to its semidouble
    days-within; the fuller greater-double octave day is treated as commemorated. Not oracle-verified.
  - **Transfer with octave:** if the Solemnity (Easter+17) were ever impeded and transferred, its octave does
    **not** move with it (octaves stay anchored / are curtailed). Dormant in practice — Easter+17 is a
    Wednesday that nothing outranks in paschaltide, so the Solemnity is never impeded.
  - **1955 keeps the Solemnity:** the derived 1955 edition keeps the Solemnity of St Joseph (octave suppressed)
    because the dataset models the **pre-1-May-1956** state — St Joseph the Worker is held out and Ss Philip &
    James are restored to 1 May. This diverges *by construction* from the historical 1956 calendar (which
    replaced the Solemnity with St Joseph the Worker) on this one feast; it is a documented modelling choice,
    not a defect.
  - **Latin heading orthography** (`Solemnitatis Sancti Ioseph`; `Septem Dolorum Beatae Mariae Virginis`) is
    display text pinned to the standard pattern, not transcribed from a scanned 1920 *typica*; it does not
    affect grade, date, colour, or precedence. As for the privileged octaves, the Sunday within the octave
    keeps its own office and the octave's *Dominica infra Octavam* commemoration on that Sunday is not modelled.
- **Pre-1955 Saturday Office of Our Lady + the two-simples tie-break (Divino Afflatu 1954 / Cum nostra 1955,
  #453):** the votive *Officium Sanctae Mariae in Sabbato* is built on free Saturdays, with a few deliberate
  simplifications to pin against the full-year Divino-Afflatu oracle sweep before the fixture is frozen:
  - **Vigil roster drives exclusion by the data, not a list:** a Saturday carrying a real vigil or Ember day
    excludes the Office because that higher office beats the lady office at precedence — so the exclusion is
    correct for whatever the corpus marks as a vigil/Ember day, never a hardcoded roster. The precise 1954
    vigil roster and the *Cum nostra* **retained**-vigil list are inherited from the sanctoral data, not
    re-asserted here; a residual vigil-set difference would surface a wrong Saturday in the oracle sweep.
  - **1955 Epiphany-octave suppression:** *Cum nostra* retained only the Christmas/Easter/Pentecost octaves, so
    the Epiphany octave is gone in 1955 — but the Saturday Office's Christmas → Epiphany exclusion is a single
    civil-date gate (24 Dec – 13 Jan) shared by both editions, modelling the block as occupied. Whether a
    specific early-January 1955 Saturday is instead free is not distinguished; to be checked in the sweep.
  - **Anticipated Sunday (a temporal-engine gap the built office surfaces):** when an early Septuagesima
    squeezes out a Sunday after the Epiphany, the pre-1955 rite *anticipates* that Sunday onto the free Saturday
    before Septuagesima (e.g. the VI Sunday after the Epiphany on 13 Feb 1954). The temporal engine does not yet
    mint an anticipated Sunday, so that Saturday looks free and the lady office — correctly ranked *below* a
    semidouble — takes it. Rare; deferred to the temporal-engine scope, where an anticipated-Sunday observance
    will make the lady office yield automatically (no change needed here). Tracked in the oracle known-differences.
  - **Cosmetic season on a displaced office:** the lady office carries its own `season` (computed from the
    paschal skeleton) only for completeness — the day's reported season always comes from the temporal filler,
    never the overlay, so the lady season never drives output.
  - **Two-simples dignity — complete for the universal fixed calendar:** an offline scan of the 1954
    fixed-date placements finds exactly two same-day Simple-vs-Simple collisions, both now in the
    `dignior-simple` set: 19 January (Ss Marius & Co. over St Canute) and 21 October (St Hilarion over
    Ss Ursula & Co.). Under 1955 the reform abolished the simple office (former simples are
    commemorations) and no former-semidouble Simples share a date, so the tie-break is 1954-only and its
    1955 set is inert. What is **not** enumerated is a Simple pair introduced by a *particular* calendar
    overlay (it would fall back to id-string order) or a Simple displaced onto another date — neither
    occurs on the universal calendar.

## Deliberately out of scope

Conscious decisions to *not* build something — recorded so the boundary is a choice, not an oversight.

- **Solar / sunset times:** the exact clock time of sunset (for First Vespers timing, or the end of a
  fast) is **out of scope**. Computing it requires the observer's geolocation, which would break the pure
  `(date, edition)` cache key that makes the engine reproducible and its output byte-identical. The engine
  stays location-independent; if solar times are ever wanted they belong on the **client**, computed from
  the day the engine already returns, not in the resolver.
- **Commercial billing / paid tiers:** metered billing and paid feature tiers are **out of scope**. The
  platform is **AGPL-3.0** and privacy-first; plain **API quotas** are sufficient to protect the service
  without accounts, payment, or usage tracking. Revisit only if hosting costs demand it — a change to
  policy, not to the engine.

## Confidence

- Days corroborated by **≥2 independent oracles** are **high** confidence; single-oracle days are
  **medium**; unresolved oracle divergences are **flagged**. The coverage/confidence report (once the
  corpus lands) quantifies this per year and edition.

_This document is updated as milestones close and as corrections (see `ERRATA.md`) reveal new edge cases._
