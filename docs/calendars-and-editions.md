# Calendars and editions

Directorium can resolve the same date under several **editions** of the traditional
Roman calendar, and layer a community's **particular calendar** over any of them. This
page explains, in plain terms, what each one is, who follows it, and the value you pass
to select it (the `$rubricSystem` and `$calendar` selectors in the
[integration guide](integration-guide.md)). Every liturgical fact behind these is cited
in the source ledger, [SOURCES.md](../SOURCES.md).

## Editions (the `$rubricSystem` selector)

An **edition** is a rules-family: the rubrics that govern precedence and the calendar
data of a given era. Four are built today: three pre-conciliar Roman editions and the
reformed **Novus Ordo** (Ordinary Form).

### 1962 — Rubricae 1960 · *the default*

- **Select with:** `null` (the default), `1962`, `1960`, `rubricae-1960`, or the URN
  `roman:rubricae-1960`.
- **What it is:** the calendar of the 1962 *editio typica*, governed by the 1960 Code of
  Rubrics. Feasts are ranked in the simplified **four-class scheme** (I–IV).
- **Who follows it:** the traditional Roman rite as celebrated today — including the
  SSPX, FSSP, and ICKSP, each with its own overlay on top (below). If you are unsure
  which edition you want, this is it.

### 1954 — Divino Afflatu

- **Select with:** `1954`, `divino-afflatu`, or the URN `roman:divino-afflatu`.
- **What it is:** the calendar as it stood under St Pius X's *Divino Afflatu* (1911),
  before the 1955 simplification. It keeps the **full octaves and vigils** and the older
  **double / semidouble / simple** rank ladder, so many more days carry an octave or a
  vigil than under 1962.
- **Who follows it:** communities and scholars working from the pre-1955 books (the older
  Holy Week, the fuller sanctoral). Historical window 1913–1955.

### 1955 — interim rubrics (*Cum nostra hac aetate*)

- **Select with:** `1955`, `rubricae-1955`, or the URN `roman:rubricae-1955`.
- **What it is:** the 1955 reform that simplified the 1954 calendar — most octaves
  removed (three kept), vigils cut to seven, the semidouble rank suppressed — the
  intermediate state between Divino Afflatu and the 1960 rubrics.
- **Who follows it:** those reconstructing the brief 1956–1960 interim period.

### 2002 — Novus Ordo (Ordinary Form)

- **Select with:** `novus-ordo`, `ordinary-form`, `2002`, or the URN `roman:novus-ordo-2002`.
- **What it is:** the reformed **General Roman Calendar** of the 2002 *editio typica tertia* —
  a distinct rules-family, not a diff of 1962. Five seasons with a two-block **Ordinary Time**;
  the **solemnity / feast / memorial / optional-memorial** grades; the Table of Liturgical Days
  for precedence; and the reformed penitential discipline (`cic-1983` — fast on Ash Wednesday and
  Good Friday only). A weekday's electable **optional memorials** are surfaced in the contract's
  `optionalMemorials` list; the day itself resolves to the obligatory office or the feria.
- **What it excludes:** universal decrees promulgated *after* the 2002 typical edition (e.g. the
  2018 Mary, Mother of the Church) — those arrive with the dated decree-overlay governance (#366).
- **Who follows it:** the Ordinary Form of the Roman rite as celebrated across the Latin Church.
  Cross-checked against two independent open-source calendar engines (#260). Historical window
  2002–present.

## Particular calendars (the `$calendar` overlay selector)

An **overlay** is a thin layer of a community's own feasts and rank changes, resolved
*over* an edition (by default 1962) — never a separate edition. Each is documented as a
**well-attested subset** cited to the society's own published ordo, not the complete
promulgated proper. See [design/sanctoral-overlay-model.md](design/sanctoral-overlay-model.md).

| Overlay | Select with | What it adds | Source |
| --- | --- | --- | --- |
| **Generic 1962** | `generic-1962` | nothing — the identity overlay, a named preset equal to the universal calendar (a baseline the others diff from) | — |
| **SSPX** | `sspx` | St Pius X (3 Sep) and the Seven Sorrows (15 Sep) elevated to I class | cross-checked against the live SSPX ordo feed |
| **FSSP** | `fssp` | the Chair of St Peter (22 Feb) raised II → I | the FSSP's published ordo |
| **ICKSP** | `icksp` | de Sales (29 Jan), Aquinas (7 Mar), Benedict (21 Mar), and Thérèse of Lisieux (3 Oct) raised to I class | the Institute's published ordo |

All overlays carry a URN of the form `directorium:overlay:roman:<slug>` and can also be
selected by that URN.

> **Cum sanctissima** (the 2020 permission to keep post-1960 saints on 3rd/4th-class
> days) is **not yet available** — it needs the corpus of saints canonised after 1960,
> which a later milestone builds. It will arrive as the first resolve-time *option* (v2.3).

## How they combine

The two selectors are orthogonal, so you can, for example, resolve the SSPX calendar
under the 1962 edition (the usual case) simply by passing `$calendar = 'sspx'` and
leaving `$rubricSystem` at its 1962 default. Passing neither gives the universal 1962
calendar. See the [integration guide](integration-guide.md) for the call signatures and
worked examples.
