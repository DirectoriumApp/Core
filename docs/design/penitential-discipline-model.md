# The penitential-discipline model

How Directorium resolves a day's **fast and abstinence** (Epic #247). The short of
it: fasting is **canon law, not rubric**, so it lives on its own axis — one discipline
shared across the rubric editions — and the obligation is a per-day realization read
off the resolved calendar, never a duplicated per-edition attribute.

## Why a separate axis

The rubric editions (1954 Divino Afflatu, 1955 Cum nostra, 1962 Rubricae 1960) govern
the **calendar and Office**. The law of fast and abstinence is a different thing: the
**1917 Code of Canon Law** (cann. 1250–1254), which stood unchanged from 1918 until
*Paenitemini* relaxed it in 1966. One discipline therefore governs all three of our
editions. Bolting fasting onto `RubricSystem` would have triplicated identical rule
data and mismodelled the concept — it would break the day someone wants "1962 rubrics
with the modern fasting law", or when the Novus Ordo calendar arrives on a different
discipline. So the discipline is its own axis:

- **`RubricSystem::penitentialDiscipline()`** maps an edition to a discipline key.
  All traditional editions map to `cic-1917`.
- A later era (Paenitemini, the modern three-day norm, a Novus Ordo discipline) is a
  **new data folder and a new key** — no engine change.

## The pieces

- **Data — `data/corpus/disciplines/<key>/`** (generator-produced, born-cited). A
  `fasting-rules.ndjson` with one obligation per named condition, and a
  `discipline.json` meta (URN, name). Authored as cited YAML in
  `tools/generator/facts/disciplines/<key>.yaml`; every rule cites its canon and passes
  the same schema + provenance gates as the rest of the corpus, and travels with the CC0
  dataset (so downstream bundles get it for free).
- **`Discipline\PenitentialDiscipline`** — the read seam over that data: each rule's
  obligation (`fast`, `Abstinence`, `cite`) and the set of vigils that carry a fast.
- **`Discipline\Abstinence`** — the grade (`none` / `partial` / `full`), comparable by
  severity so a day meeting several rules keeps the strictest.
- **`Discipline\FastingObligation`** — a day's realized obligation: `fast`, its
  `Abstinence`, the `reason` (the rule that applied), the discipline URN, and the cited
  canon. Minted only when something applies.
- **`Discipline\FastingResolver`** — derives the obligation from a resolved day.

## Resolution — emergent per-edition correctness

`FastingResolver::resolve()` reads the **already-resolved day** — its weekday, its
season, and the offices in play (their `kind` and `id`) — and computes the penitential
conditions that hold, as rule-name tags:

- **Friday** — every Friday (abstinence).
- **Ash Wednesday** — by id.
- **Lenten weekday** — season `lent` or `passiontide`, split Fri/Sat (major) vs Mon–Thu
  (minor). Sundays are exempt.
- **Ember day** — an office of `kind: ember-day`.
- **Vigil** — an office of `kind: vigil` whose id the discipline lists as a fasting
  vigil (Christmas, Pentecost, Assumption, All Saints).

Each tag is looked up in the discipline; the applicable obligations fold into one (fast
if any prescribes it, the strictest abstinence, the most characteristic rule's reason
and citation). The resolver stamps it onto the `LiturgicalDay`, and the contract's
`fasting` slot serialises it.

Because it reads the day **as the edition resolved it**, per-edition correctness is
**emergent, not coded**: the 1960 rubrics suppressed the All Saints vigil, so under 1962
there is no such vigil day and no fast attaches — while under 1954, which keeps it, the
same discipline data produces the fast. The discipline is never forked per edition.

## The 1917 discipline (`cic-1917`)

Per canon 1252: complete abstinence on every **Friday** (§1); **fast and complete
abstinence** on Ash Wednesday, the Fridays and Saturdays of Lent, the Ember days, and
the vigils of Christmas, Pentecost, the Assumption, and All Saints (§2); **fast with
partial abstinence** on the other weekdays of Lent (§3). Sundays are never days of fast
or abstinence.

## Known limitations

Tracked in `KNOWN-LIMITATIONS.md`: the c.1252 §4 holyday-of-obligation **dispensation**
(outside Lent) is not yet modelled, and the **September Ember days** carry no fast
because the temporal engine does not yet place them (#22). Both are additive — the data
model and the emergent-per-edition design already accommodate them.
