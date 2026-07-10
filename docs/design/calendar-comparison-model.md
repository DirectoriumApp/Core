# Calendar-mode comparison

_Status: active. The v1.0 **calendar-mode** comparison: resolve a date, range, or
sequence under two or more editions and report where they diverge. It needs no text
layer — only ≥2 calendar engines, which exist today (1954 Divino Afflatu, 1955
interim, 1962 Rubricae 1960) — and is the **pattern-setter** for the later rite- and
office-mode diff (comparison Epic #307, v2.0), which generalises the same taxonomy to
structural units and texts. Epic #311 (children #312 diff, #313 slider)._

## What it compares

Calendar mode diffs only what two *calendars* can differ on for a civil date — the
small, text-free set in [`ComparisonField`](../../src/Compare/ComparisonField.php):

| Field | Source (day contract) | Why it can differ across editions |
| --- | --- | --- |
| `feast` | principal `celebration[0].id` | the reforms suppressed octaves, vigils, and feasts, so a different office wins the day |
| `rank` | `celebration[0].rankOrdinal` | the same feast is ranked differently (e.g. the Vigil of the Assumption is II class in 1962, IV in 1954/1955) |

The diff is keyed on the stable feast **id**; a cell also carries the feast's Latin
display name (`celebration[0].names.la`) for rendering, but that is identity — it is
already a frozen field of the day contract — not a proper text. A `rank` divergence
reports the difference between the `rankOrdinal`s the two editions' contracts emit; the
pre-1955 double/semidouble scale and the 1962 I–IV scale are only loosely commensurable,
so a rank divergence means "these editions assign a different class ordinal here", not a
claim that the two scales map one-to-one.
| `colour` | `celebration[0].colour.base` | follows from which office wins |
| `commemorations` | sorted `commemoration[*].id` | the 1960 rubrics cut commemoration counts sharply |
| `season` | day-level `season` | shared where the concept is shared, so it aligns by token ([`season-vocabulary.md`](season-vocabulary.md)) |

Every field is read from the **published day contract**
([`output-contract.md`](output-contract.md)), so a comparison diffs exactly what a
consumer of each edition sees — never an internal representation that could drift from
the frozen output.

## The taxonomy

The unit is the **cell** — one edition's resolved view of one date
([`EditionDayCell`](../../src/Compare/EditionDayCell.php)). Comparison is **symmetric
and N-way**: for each field, gather every cell's value and mark the field *divergent*
when they do not all share one value. There is no privileged "base" edition, so N
editions compare in one pass rather than N−1 directional diffs. Commemorations compare
as a **sorted list**, so the set is order-independent.

Two shapes consume the taxonomy:

- **Range** — [`CalendarComparator`](../../src/Compare/CalendarComparator.php) →
  [`CalendarComparison`](../../src/Compare/CalendarComparison.php) of
  [`ComparedDay`](../../src/Compare/ComparedDay.php)s, each carrying its per-edition
  cells and its divergent fields, plus the roll-up (`divergentDayCount`) an
  "only-changed" filter and summary need.
- **Sequence** — [`SequenceComparator`](../../src/Compare/SequenceComparator.php) →
  [`ComparedSequence`](../../src/Compare/ComparedSequence.php) of
  [`SequencePoint`](../../src/Compare/SequencePoint.php)s along one axis (years or
  editions). A sequence is **directional**: each point past the first is tagged with
  the fields that changed from its predecessor, which is what the Site year-slider
  needs to mark where a change lands.

## Why it prefigures the rite/office diff

The v2.0 rite- and office-mode diff (a `DiffResult` over ordered structural units) will
reuse three ideas proven here: a **field/unit taxonomy** of what can differ; a
**symmetric N-way** comparison with **no privileged base**; and **directional
change-tagging** for the slider. Calendar mode is the cheap first instance — a flat
per-day cell instead of a unit tree, and no text availability to track — so the shape
is exercised before the expensive modes commit to it.

## Boundaries

- **Editions only, in v1.0.** The comparison axis is the rubric system (edition); a
  particular-calendar overlay (SSPX, FSSP) is layered *over* an edition and is not a
  comparison axis here. The cell is built under the universal calendar for its edition.
- **No text layer.** Calendar mode never reads propers, chant, or lectionary — that is
  rite/office mode. It diffs only the fields above; the feast's own display name is
  carried for rendering but is not a text layer (it is identity, already in the day
  contract).
- **Resolution is memoised per (edition, year)** so a range or sequence resolves each
  edition-year at most once ([`EditionResolver`](../../src/Compare/EditionResolver.php)).
