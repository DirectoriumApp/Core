# Sources

Directorium is accurate **because it cites its sources**. This ledger is the master register of the
authoritative works the calendar and corpus are built from. Every liturgical datum in the corpus (a
feast's date/rank/colour, a rubric, a text) carries a **source key** that points at an entry here, so any
value can be traced back to the printed book that establishes it.

This is a living document: entries are added as each edition and corpus lands. The structured citation on
each datum (see `docs/design/platform-urn-scheme.md`, URN `directorium:source:<key>`) foreign-keys into the
**Key** column below.

## How to read an entry

| Field | Meaning |
|-------|---------|
| **Key** | Stable identifier used by data records and the API (`directorium:source:<key>`). |
| **Work / edition** | The authoritative book and its edition. |
| **Use** | `text` = we may transcribe public-domain/freely-licensed text; `reference` = consulted to establish facts only, **never transcribed**; `oracle` = used to cross-check generated output in validation. |
| **Licence / status** | Copyright status, and what we may do with it under the clean-room policy. |

## Primary sources — rubrics & calendar

| Key | Work / edition | Use | Licence / status |
|-----|----------------|-----|------------------|
| `rg-1960` | *Rubricae Generales Missalis et Breviarii Romani* / *Codex Rubricarum* (promulgated 25 Jul 1960). | reference | Official liturgical legislation; facts (ranks, precedence, transfer, commemoration limits) may be stated and cited. |
| `mr-1962` | *Missale Romanum*, editio typica 1962 (Typis Polyglottis Vaticanis). | reference | Latin editio typica. Facts and rubrics may be cited; text transcription only from confirmed public-domain matter. |
| `br-1961` | *Breviarium Romanum*, editio typica 1961 (post-1960 rubrics). | reference | As above; the 1962 Office psalter and structure are established here. |
| `da-1911` | Pius X, apostolic constitution *Divino Afflatu* (1 Nov 1911) + the reformed Roman psalter. | text | Public domain (pre-1930). Establishes the 1911 psalter redistribution. |
| `mr-1920` | *Missale Romanum*, editio typica 1920 (post–Divino Afflatu, pre-1955). | text | Public domain (pre-1930). |
| `mr-1570` | *Missale Romanum* of Pius V, editio princeps 1570 (Tridentine). | text | Public domain. Establishes the Tridentine baseline. |
| `mart-rom` | *Martyrologium Romanum* (editio typica; pre-1930 printings). | text | Public domain printings; later reprints are `reference`. |
| `cic-1917` | *Codex Iuris Canonici* (1917), the law of fast and abstinence (cann. 1250–1254). | reference | The traditional penitential discipline in force across the 1954/1955/1962 editions (until *Paenitemini*, 1966). Facts (which days are fast/abstinence) may be cited; the canons' text is never transcribed. Governs the `roman:cic-1917` discipline (#248). |

## Oracles — independent cross-checks (validation only)

These are **not** transcribed into the corpus; the validation harness compares Directorium's generated output
against them day-by-day to prove correctness (see `ERRATA.md` for documented, legitimate differences).

| Key | Source | Use | Notes |
|-----|--------|-----|-------|
| `computus-easter-days` | PHP `easter_days()` (Meeus/Jones/Butcher via the C runtime). | oracle | Independent Easter computation, cross-checked against `Computus`. |
| `calendrical-tables` | Published Gregorian computus tables — Golden Number, Epact, Solar Cycle, Dominical Letter, Roman Indiction — as printed in the *Martyrologium Romanum* front matter and standard ecclesiastical almanacs. | oracle | Public-domain mathematical facts of the Gregorian reckoning; cross-checks the calendrical block (#246). |
| `divinum-officium` | Divinum Officium reference implementation (divinumofficium.com). | oracle | Open-source; broad edition coverage. |
| `missalemeum` | Missale Meum calendar/ordo (missalemeum.com). | oracle | 1962 calendar cross-check. |
| `sspx-ordo` | SSPX ordo feed. | oracle | 1962 calendar cross-check; some particular-calendar differences (allowlisted). |

## Clean-room policy

Directorium's engine and data are written from **published rubrics and liturgical fact**, never ported from
another engine's source code. **Copyright-restricted** editions and translations (e.g. modern hand-missal
translations) may be **consulted as reference** to establish facts — that a feast is I class in a given
edition, that a rubric reads a certain way — but their **text is never copied**. Public-domain and
freely-licensed sources may be transcribed. When a source's status is uncertain, it is treated as
`reference` until confirmed. See [CONTRIBUTING](https://github.com/Directorium/.github/blob/main/CONTRIBUTING.md).
