# Known limitations

An honest register of what Directorium does **not** yet cover, or covers with lower confidence. Trust comes
from disclosing gaps, not hiding them. Items here are candidates for the roadmap and are surfaced (where
relevant) as `confidence` flags in the data and in the API's coverage report.

## Coverage

- **Editions:** the engine begins with **1962 (Rubricae 1960)**. Divino Afflatu (1911/1954), Pius XII 1955,
  Tridentine (1570/~1906), and the Novus Ordo are roadmapped, not yet built.
- **Pillars:** the **calendar** comes first; the **Missal** (Mass propers/ordinary) and **Breviary**
  (Divine Office) text layers are later milestones. The Office psalter is initially the 1962 scheme only;
  the pre-1911 and Divino Afflatu psalters are a later milestone.
- **Particular calendars:** the **universal** calendar comes first. Regional/diocesan calendars, religious
  order propers, and society presets (SSPX/FSSP/…) are roadmapped.
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
