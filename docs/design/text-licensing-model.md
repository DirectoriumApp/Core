# The text licensing model

_Status: reserved. The platform-wide rights model for liturgical **text** — what
may ship, under what licence, and how a text that cannot ship degrades. It fixes
one rule for the whole platform so the corpus, the output contract's `text`/`chant`
fill-shape, and the comparison tool all inherit it instead of deciding case by
case. Extends the corpus provenance gate (Epic #109)._

The load-bearing distinction is between **uncopyrightable liturgical facts** and
**copyrighted liturgical texts**. Everything the engine needs to *resolve and
compare* a day — the structure, the incipits, the pericope citations, the
calendar facts — is fact, and ships CC0. The full running text of a modern proper
is an expressive work under copyright, and ships only under an explicit licence or
from a verified public-domain source. The whole platform is deliverable — the
structural comparison, the calendar, the incipits — **without** any copyrighted
full text; full text is always a *stretch outcome*, never a milestone gate.

## The `textAvailability` taxonomy

Every text leaf (per role, per locale — see the output contract's
[text & chant fill-shape](output-contract.md#text--chant-fill-shape-availability-aware))
declares its availability:

| Value | What ships | Reader sees | Comparison tool |
| --- | --- | --- | --- |
| `full` | the complete text | the whole proper, rendered | diffs the full text on this side |
| `incipit` | the opening words only | the incipit + a citation | aligns and diffs by incipit + structure |
| `citation-only` | a bare reference (book/section/pericope) | the citation, no words | aligns by citation; no text diff |
| `licence-required` | nothing (a licence exists but is not held) | the citation + a "why unavailable" affordance | aligns structurally; text withheld this side |

`full` and `incipit` are what the platform *ships*; `citation-only` is the
irreducible fact when even an incipit would be a substantial excerpt;
`licence-required` is the fail-safe when a text is known, licensable, but not
licensed in this build. The reader is never shown a dead end — every value below
`full` still renders a citation and, where relevant, an incipit.

## The core rule: facts are CC0, modern texts are copyrighted

**Uncopyrightable facts → CC0.** Structure (the order and presence of parts),
incipits (the customary opening words used as a *reference*), pericope citations
(book, chapter, verse), and calendar facts (dates, ranks, colours, seasons) are
facts, not expression. They are dedicated to the public domain under **CC0-1.0**,
consistent with the corpus dataset ([`corpus-schema.md`](corpus-schema.md)).

**Full post-1962 liturgical texts → copyrighted.** The running text of the modern
books is under copyright and may **not** be transcribed without a licence:

- the **Missale Romanum** 1969 / 2002 Latin *editio typica*, and the **Nova
  Vulgata** — Libreria Editrice Vaticana (LEV);
- the **ICEL** English translations of the Missal and other rites;
- the **Liturgia Horarum** (Latin *and* vernacular — LEV for the Latin, the
  bishops' conferences and ICEL for the vernacular).

These ship **only** under an explicit licence held for the build, or from a
**verified public-domain source**. A `reference`-rights source may be consulted
to establish a *fact* (a rank, a structure, a citation) but its text may never be
transcribed — the same rule the corpus already enforces on `reference` sources.

**Pre-1962 books are public domain by age.** The pre-1962 editions (see the table
below) are out of copyright, so their **full texts** may ship freely, given a
citation to the source edition. This is why the structural comparison and the
1962 text layer are fully deliverable while the modern text layer waits on
licences.

## The CI fail-closed gate

The build **fails closed** on rights, extending the corpus provenance gate
(#109): **any post-1962 text string without a licence record fails the build.**
A string is admitted only if it is either (a) a fact type that is CC0 by the rule
above, (b) drawn from a verified public-domain source, or (c) covered by an
explicit licence record naming the holder, scope, and term. A string that is none
of these is a build error, not a silent omission — the same fail-closed posture
as an uncited datum in the corpus.

Because the gate is fail-closed, a missing licence can never *leak* copyrighted
text; it can only *withhold* it (`licence-required`). A full-text licence is
therefore always a **stretch outcome** that upgrades leaves from `citation-only`
/ `licence-required` to `full` — it is **never a milestone gate**, and its absence
never blocks the structural comparison or the incipits, which remain fully
deliverable.

## Degradation rules

A text that is missing, `citation-only`, or `licence-required` degrades
gracefully rather than vanishing:

- It renders as **incipit + citation + a "why unavailable" affordance** — the
  reader always learns *what* the text is and *why* its body is not shown (age,
  copyright held elsewhere, licence not held), with a path to the source.
- In the comparison tool, **each diff node carries a per-side `textAvailability`**.
  Two sides can differ (a pre-1962 side `full`, a post-1962 side `licence-required`);
  the node still aligns structurally and by incipit, and the renderer degrades
  only the side that must be withheld. The diff never fabricates a body to match.

## Per-edition public-domain status

A working PD-status table for the principal books (verify at ship time; "verify"
means treat as copyrighted until confirmed):

| Book / edition | Status |
| --- | --- |
| Tridentine 1570 / ~1906 | public domain (age) |
| Divino Afflatu 1911 | public domain (age) |
| 1954 | public domain (age) |
| 1955 (Pius XII simplification) | public domain (age) |
| 1962 (Rubricae 1960) | public domain (age) |
| 1965 / 1967 interim | **verify** (transitional; confirm before shipping text) |
| Missale Romanum 1969+ (1969 / 1975 / 2002 / 2008) | **copyrighted** (LEV / ICEL) |
| Liturgia Horarum (1971+) | **copyrighted** (LEV / conferences / ICEL) |
| Nova Vulgata | **copyrighted** (LEV) |
| Rituale Romanum | **per-edition** — early editions PD by age; the 1952 late typical edition → **verify** |

The pre-1962 rows carry **full text** (with citation); the copyrighted rows ship
`citation-only` / `incipit` until a licence is held, then upgrade to `full`.
