# Season vocabulary

_Status: active. Why the output contract's `season` field is an **open,
edition-scoped vocabulary** rather than a closed enum, what tokens each rubric
system admits, and the rule for adding more without breaking the frozen contract.
Companion to [`output-contract.md`](output-contract.md) (the closed-vs-open enum
policy and the bump rules) and [`edition-governance.md`](edition-governance.md)
(the edition axis this vocabulary is namespaced by). Tracking issue #364; it blocks
the contract-1.0 promotion (#90)._

## The problem a closed enum creates

The traditional Roman year has eight seasons (_tempora_), and through the 0.x line
`season` was a closed eight-member enum. Freezing that set at contract 1.0 would
make **every** later season a major, breaking change — including the Novus Ordo's
`ordinary-time`, which has no traditional equivalent. That is exactly the breaking
change a 1.0 freeze exists to prevent: the calendar-mode comparison (Epic #311) and
the future Novus Ordo engine (v1.1) both need to emit a `season` the 1962 set never
contained, and neither should force consumers onto a v2 contract.

So `season` is reclassified, **before the freeze**, from a closed enum to an open
vocabulary. The reclassification is source-compatible for every edition shipped
today: the 1962/1954/1955 subsets are unchanged and the 1962 golden fixtures stay
byte-identical.

## The two rules

**1. Tokens are bare and shared wherever the concept is shared.** `advent`, `lent`,
and `eastertide` denote the same liturgical reality in every edition, so the token
string is identical across editions and the comparison diff aligns seasons **by
token**, with no per-edition remapping table. A token is only distinct when the
_concept_ is distinct: the Novus Ordo's `ordinary-time` is genuinely not any
traditional tempus, so it is its own token — never a rename of `pentecost` or
`epiphany`.

**2. Each edition declares the subset it admits.** The vocabulary is namespaced by
the contract's existing `edition` field, not by mangling the token. An edition emits
only the seasons in its declared subset; a token absent from a subset simply never
appears under that edition. This is what "edition-scoped" means — the same open
vocabulary, filtered per edition.

Together these keep cross-edition comparison honest (shared concepts compare as
equal; genuinely new concepts appear as additions) while letting the vocabulary grow.

## The registry

The registry lives in code at
[`src/Temporal/SeasonVocabulary.php`](../../src/Temporal/SeasonVocabulary.php) and is
the single source of truth that [`Season`](../../src/Temporal/Season.php) validates
against. `Season::fromString()` accepts any token in the **open union** (valid for at
least one edition); `Season::forEdition($token, $editionUrn)` additionally enforces
the edition's subset.

### The open union (all tokens registered today)

| Token | Tempus |
| --- | --- |
| `advent` | Advent |
| `christmastide` | Christmastide |
| `epiphany` | Time after Epiphany |
| `septuagesima` | Septuagesima (pre-Lenten fore-season) |
| `lent` | Lent |
| `passiontide` | Passiontide (Passion Week + Holy Week) |
| `eastertide` | Eastertide (Paschaltide) |
| `pentecost` | Time after Pentecost |

### Per-edition subsets

| Edition (URN) | Subset |
| --- | --- |
| `roman:divino-afflatu` (1954) | the full traditional eight |
| `roman:rubricae-1955` (1955) | the full traditional eight |
| `roman:rubricae-1960` (1962) | the full traditional eight |

Every **built** edition today shares the same eight tempora, so the union equals the
traditional set and the reclassification changes no output. Divergence begins only
with editions not yet built.

### Illustrative future subset (not yet registered)

The Novus Ordo is the reason the vocabulary is open. When its engine lands (v1.1) it
registers its own subset — **it does not touch the rows above**:

| Edition (URN) | Subset |
| --- | --- |
| `roman:novus-ordo-*` (future) | `advent` · `christmastide` · `lent` · `eastertide` · **`ordinary-time`** — no `septuagesima`, no `passiontide`, no `epiphany`/`pentecost` as distinct tempora |

This row is documentation, not code: the traditional editions are authored from
verified fact, and the Novus Ordo subset is registered by the engine that models it,
not fabricated ahead of it. It is shown here only to make the shared-token rule
concrete — `advent`/`lent`/`eastertide` are the same tokens, `ordinary-time` is the
one genuine addition.

## Adding to the vocabulary

- **Register a new edition subset** — add a row to `SeasonVocabulary::SUBSETS` keyed
  by the edition URN. If it uses only tokens already in the union, this is a **patch**
  to the contract (no new observable token).
- **Register a new token** (e.g. `ordinary-time`) — it appears in the union and in at
  least one edition's subset. By the open-enum rule in
  [`output-contract.md`](output-contract.md#bump-rules) this is a **minor** contract
  bump: additive, never breaking, because no consumer relied on the token's absence.
- **Never** remove or rename a published token — that is a major change, forbidden
  before v2 (published season tokens are part of the stability promise).
