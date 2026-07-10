# Roadmap

_A plain-language overview of where Directorium Core is headed. Each version links to its tracking
milestone and the issues that make it up (issue links are added once the backlog is imported)._

_Last updated: 2026-07-02_

## Release train
- **R1 — Groundwork.** Finish the 1962 engine (cited corpus, multi-oracle validation, the resolution
  trace) and lock the pre-launch contract decisions (open-season vocabulary, textAvailability,
  edition governance, lectionary indicators).
- **R2 — 3mi.org.** The 1962 calendar with the **SSPX** overlay first — Core v0.2.0 as the release
  vehicle behind the Ordo plugin on 3mi.org.
- **R3+.** Everything else, in the build order below.

## 🚧 In progress — [v0.1.0](https://github.com/Directorium/Core/milestone/1)
**The 1962 Roman engine — accurate and provable.** A from-scratch engine that computes the traditional
Roman calendar for any date — feast, rank, colour, season, commemorations, and occurrence/concurrence —
independently re-validated to 100% against multiple external oracles. This milestone also lays the
**accuracy & authority foundation**: a cited CC0 corpus + generator over the `SOURCES.md` ledger,
whole-corpus **data-quality gates**, multi-oracle **re-proving with a coverage/confidence report**, the
**show-your-work resolution trace**, and the **cited 1962 dataset** itself.

## 🗓️ Next — [v0.2.0](https://github.com/Directorium/Core/milestone/3)
**Particular calendars — SSPX first.** The **3mi.org release vehicle**. All four overlays are
**✅ shipped** — SSPX (vs its live ordo feed), FSSP and ICKSP (proper-patron elevations, vs pinned
proper-day fixtures), and Generic 1962 (the identity overlay) — each a distinct `validate (overlays)`
CI gate. The **Cum sanctissima** toggle (#85) is **deferred to v2.3.0**: the 2020 decree is fundamentally
about saints canonized *after 1960* (not in the 1962 calendar), so it needs the post-1960-saints corpus
built there before the toggle can resolve any eligible saint.

## 🔭 Future
- **[v0.3.0](https://github.com/Directorium/Core/milestone/2) — 1954 & 1955 engines. ✅ Shipped (#453).**
  The pre-1955 (1954, Divino Afflatu) and 1955 interim engines run alongside 1962 from one rubric-generic
  codebase, with per-edition precedence rules and commemoration limits. All three editions are now built
  and publicly resolvable; 1962 stays byte-identical.
- **[v0.4.0](https://github.com/Directorium/Core/milestone/10) — Calendrical & fasting layers.** The
  calendrical/astronomical block (Golden Number, Epact, Dominical Letter, Indiction, lunar age) and
  per-edition fasting & abstinence.
- **[v1.0.0](https://github.com/Directorium/Core/milestone/4) — Platform launch (traditional-complete).**
  Hardening for the first complete public release (cut together with Api, Site, and Ordo) — the
  **contract & API freeze**, the **citable/reproducible dataset**, and **calendar-mode comparison**
  across the traditional editions (1962 / 1954 / 1955).
- **[v1.1.0](https://github.com/Directorium/Core/milestone/16) — Novus Ordo calendar engine.** Ranks,
  colours, Ordinary Time, cycle computation, and **edition governance** (editio typica snapshots + dated
  decree overlays). Enables TLM↔NO calendar comparison. (Texts excluded — see v1.6.0.)
- **[v1.2.0](https://github.com/Directorium/Core/milestone/5) — Text infrastructure + Little Office of
  the BVM.** The bilingual text assembler, the first full-text office pilot, the **Office of the Dead**,
  and **multi-vernacular translations**.
- **[v1.3.0](https://github.com/Directorium/Core/milestone/6) — Roman Missal (bilingual Mass).**
  Ordinary, Propers, prefaces, sequences & tracts, ritual Masses — plus the **first-class lectionary
  model**.
- **[v1.4.0](https://github.com/Directorium/Core/milestone/7) — Full Roman Breviary (bilingual).** All
  hours, psalter, propers, commons, and hymns (incl. preces/suffrages/Quicumque accuracy).
- **[v1.5.0](https://github.com/Directorium/Core/milestone/11) — Tridentine (1570 / ~1906) + interim
  rite.** The Tridentine calendar plus the 1965/67 interim-rite stub. Historical depth and the widest
  comparison span.
- **[v1.6.0](https://github.com/Directorium/Core/milestone/12) — Novus Ordo Missal texts (1969+).** The
  NO Missal texts + NO lectionary cycles (A/B/C, I/II). Bilingual where licensed/PD, else incipit +
  citation.
- **[v1.7.0](https://github.com/Directorium/Core/milestone/13) — Historical Office psalters.** The
  pre-Pius-X and 1911 Divino Afflatu psalters + the Divino Afflatu office-structure deltas.
- **[v1.8.0](https://github.com/Directorium/Core/milestone/17) — Liturgia Horarum (1971).** The Novus
  Ordo Divine Office — no Prime, the Office of Readings, the four-week psalter. Enables 1962↔LOTH office
  comparison.
- **[v2.0.0](https://github.com/Directorium/Core/milestone/15) — Comparison flagship & rite framework.**
  Calendar / rite / office comparison modes, the structural **diff engine**, and the **rite-framework
  seam** that reserves the multi-rite horizon (Western uses → order rites → Byzantine → other Eastern).
- **[v2.1.0](https://github.com/Directorium/Core/milestone/8) — Office for American Laity.** The 1888
  Baltimore Manual + Bute translation.
- **[v2.2.0](https://github.com/Directorium/Core/milestone/9) — Monastic Diurnal (1963).** Plus reserved
  full Matins/Nocturns & the Thesaurus 1977 schemas.
- **[v2.3.0](https://github.com/Directorium/Core/milestone/14) — Supporting corpora.** Roman
  Martyrology, Gregorian chant (GABC), devotions, particular/regional calendars, stational churches, and
  calendar-linked blessings.
- **[v2.4.0](https://github.com/Directorium/Core/milestone/18) — Rituale Romanum.** The Rituale Romanum
  corpus + the Pontificale (reserved).
- **[v3.0.0](https://github.com/Directorium/Core/milestone/19) — Rite framework: Western uses
  (reserved).** Ambrosian, Mozarabic, Sarum, Braga, Lyonese, Carthusian.
- **[v3.1.0](https://github.com/Directorium/Core/milestone/20) — Rite framework: religious-order rites
  (reserved).** Dominican, Norbertine; Cistercian/Carmelite overlays.
- **[v3.2.0](https://github.com/Directorium/Core/milestone/21) — Rite framework: Byzantine (reserved).**
  Euchologion / Horologion / Menaion / Octoechos / Triodion / Pentecostarion / Typikon — plus the
  East↔West comparison (Roman Mass↔Divine Liturgy; Gregorian↔Julian Paschalion).
- **[v3.3.0](https://github.com/Directorium/Core/milestone/22) — Rite framework: other Eastern
  (reserved).** Coptic, Syriac/Maronite/Chaldean, Armenian, Ge'ez.

## ✅ Released
_None yet._
