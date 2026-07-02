# Known limitations

An honest register of what Introibo does **not** yet cover, or covers with lower confidence. Trust comes
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
