// Transform hand-authored sanctoral facts into the three corpus record shapes:
// the edition-invariant identity (Layer 1) and, per edition, the attributes
// (Layer 2, rank + colour) and the placement (month + day). The split mirrors
// the identity / edition / year model: an entry states its identity once and its
// realization per edition; the generator fans it out into the schema'd records.

/** Normalize a colour fact (a bare base string, or `{ base, roseAllowed }`). */
function colourOf(colour) {
  if (typeof colour === 'string') {
    return { base: colour };
  }
  const out = { base: colour.base };
  if (colour.roseAllowed) {
    out.roseAllowed = true;
  }
  return out;
}

/** Keep only the citation keys a given record shape is allowed to carry. */
function citesFor(cites, predicate) {
  const out = {};
  for (const key of Object.keys(cites)) {
    if (predicate(key)) {
      out[key] = cites[key];
    }
  }
  return out;
}

/**
 * Fan each sanctoral entry out into `{ identity, attributes, placement }` arrays
 * for the given edition. Throws if an entry lacks the requested edition.
 */
export function transformSanctorale(entries, edition) {
  const identity = [];
  const attributes = [];
  const placement = [];

  for (const entry of entries) {
    // Identity is edition-invariant, so emit it for EVERY entry — including one the base
    // edition does not contain (a pre-1955 observance a later reform suppressed, e.g. an
    // apostle's vigil kept only in 1954). The shared identity is the cross-edition UNION;
    // each edition selects what it observes via its own attributes and placement, and the
    // build's orphan gate proves every identity is placed by at least one edition.
    const identityRecord = {
      id: entry.id,
      kind: entry.kind,
      titulars: entry.titulars,
      names: entry.names,
      cites: entry.cites,
    };
    if (entry.aliases) {
      identityRecord.aliases = entry.aliases;
    }
    identity.push(identityRecord);

    const ed = entry[edition];
    if (!ed) {
      // Legitimately absent from the base edition — but only when the entry SAYS SO
      // explicitly (notInBaseEdition). A missing base block without that marker is an
      // authoring slip (a forgotten or mistyped edition key), so it still fails closed.
      if (entry.notInBaseEdition) {
        continue;
      }
      throw new Error(
        `entry ${entry.id} has no data for edition "${edition}" ` +
          `(set notInBaseEdition: true if it is intentionally absent from the base edition)`,
      );
    }

    const edCites = ed.cites || {};
    const attributeRecord = {
      id: entry.id,
      rank: ed.rank,
      colour: colourOf(ed.colour),
      cites: citesFor(
        edCites,
        (key) => key === 'rank' || key === 'colour' || key.startsWith('nameOverride.'),
      ),
    };
    if (ed.nameOverride) {
      attributeRecord.nameOverride = ed.nameOverride;
    }
    attributes.push(attributeRecord);

    const placementRecord = {
      id: entry.id,
      month: ed.month,
      day: ed.day,
      cites: citesFor(edCites, (key) => key === 'month' || key === 'day'),
    };
    if (ed.vigilOf) {
      placementRecord.vigilOf = ed.vigilOf;
    }
    placement.push(placementRecord);
  }

  return { identity, attributes, placement };
}

/**
 * The default normalization of a native pre-1960 grade token onto its {@see RankClass}
 * ordinal (1 = class I … 4 = class IV / commemoration). Lossy-upward — several grades
 * collapse to one class — so the generator DERIVES a legacy edition's numeric `rank` from
 * its `legacyRank` by this table, and a block supplies an explicit, self-cited
 * `rankOverride` only where the 1960 revision genuinely re-graded the feast off the
 * default. That makes `rank` a single derived fact instead of a second hand-typed one that
 * could silently disagree with the grade (a typo like `duplex-ii-classis` + `rank: 1` used
 * to pass every gate). Only the sanctoral grades are mapped; a Sunday/feria token
 * (`dominica-*`, `feria-maior`) in a sanctoral block is a fail-closed error. `vigilia` is
 * a vigil's own grade (the `kind: vigil` already carries the category) — a floor-tier office
 * that collapses to class IV; #67's precedence tiers order the fine `simplex`↔`vigilia` seam.
 */
const DEFAULT_RANK_BY_LEGACY = {
  'duplex-i-classis': 1,
  'duplex-ii-classis': 2,
  'duplex-maius': 3,
  duplex: 3,
  semiduplex: 3,
  simplex: 4,
  vigilia: 4,
  commemoratio: 4,
};

/**
 * Derive `{ rank, rankCite }` for a legacy edition block: the default-table class cited to
 * the same source as the grade, or an explicit `rankOverride` carrying its own citation.
 */
function deriveLegacyRank(entryId, editionDir, ed, edCites) {
  if (!ed.legacyRank) {
    throw new Error(`entry ${entryId}: the "${editionDir}" block must declare a legacyRank grade`);
  }
  if (ed.rankOverride !== undefined) {
    if (edCites.rankOverride === undefined) {
      throw new Error(`entry ${entryId}: rankOverride must carry cites.rankOverride`);
    }
    return { rank: ed.rankOverride, rankCite: edCites.rankOverride };
  }
  const rank = DEFAULT_RANK_BY_LEGACY[ed.legacyRank];
  if (rank === undefined) {
    throw new Error(
      `entry ${entryId}: no default RankClass for legacy grade "${ed.legacyRank}" in a sanctoral ` +
        `block (map it in DEFAULT_RANK_BY_LEGACY, or author a self-cited rankOverride)`,
    );
  }
  return { rank, rankCite: edCites.legacyRank };
}

/**
 * Co-placement referential-integrity gate (#64). A vigil's or octave's placement names
 * its bearing feast via `vigilOf` / `octaveOf`; that feast MUST be placed in the SAME
 * edition. The runtime never dereferences the link (it is a provenance pointer, not a
 * lookup), so a vigil placed in an edition whose feast it names is absent would pass every
 * other gate silently — this is the only guard. It was premature while the 1954 sanctoral
 * was a sparse diff (the suppressed apostles' vigils named feasts not yet authored for
 * 1954); now that the full sanctoral lands, co-placement is the intended state.
 *
 * @param {Array<{ dir: string, placement: Array<{ id: string, vigilOf?: string, octaveOf?: string }> }>} editions
 * @throws if any vigilOf/octaveOf target is not placed in the same edition
 */
export function checkCoPlacement(editions) {
  for (const ed of editions) {
    const placedHere = new Set(ed.placement.map((record) => record.id));
    for (const record of ed.placement) {
      for (const link of ['vigilOf', 'octaveOf']) {
        const target = record[link];
        if (target !== undefined && !placedHere.has(target)) {
          throw new Error(
            `co-placement violation in edition "${ed.dir}": "${record.id}" names ${link}="${target}", but ` +
              'that feast is not placed in this edition — a vigil/octave must be co-placed with its feast.',
          );
        }
      }
    }
  }
}

/**
 * Fan a NON-BASE edition (Core v0.3.0: 1954 / 1955) out into its `{ attributes,
 * placement }` arrays — its diff from the base edition. Identity is edition-invariant
 * and already emitted by the base pass, so this emits only the per-edition Layer-2 and
 * placement shapes.
 *
 * An entry belongs to this edition iff it carries a block under `editionDir`; entries
 * without one are simply absent from the edition (a feast the reform instituted later, or
 * one this slice has not yet authored). The block states the edition's native grade
 * (`legacyRank`, cited); the numeric {@see RankClass} `rank` is DERIVED from it (see
 * {@see deriveLegacyRank}) so the two can never silently disagree, and both are carried so
 * the 1954/1955 precedence engines can order the fine grades the four classes collapse.
 */
export function transformSanctoraleEdition(entries, editionDir) {
  const attributes = [];
  const placement = [];

  for (const entry of entries) {
    const ed = entry[editionDir];
    if (!ed) {
      continue;
    }

    const edCites = ed.cites || {};
    const { rank, rankCite } = deriveLegacyRank(entry.id, editionDir, ed, edCites);

    const cites = { colour: edCites.colour, legacyRank: edCites.legacyRank, rank: rankCite };
    for (const key of Object.keys(edCites)) {
      if (key.startsWith('nameOverride.')) {
        cites[key] = edCites[key];
      }
    }

    const attributeRecord = {
      id: entry.id,
      rank,
      legacyRank: ed.legacyRank,
      colour: colourOf(ed.colour),
      cites,
    };
    if (ed.nameOverride) {
      attributeRecord.nameOverride = ed.nameOverride;
    }
    attributes.push(attributeRecord);

    const placementRecord = {
      id: entry.id,
      month: ed.month,
      day: ed.day,
      cites: citesFor(edCites, (key) => key === 'month' || key === 'day'),
    };
    if (ed.vigilOf) {
      placementRecord.vigilOf = ed.vigilOf;
    }
    placement.push(placementRecord);
  }

  return { attributes, placement };
}

/**
 * The Cum nostra hac aetate (1955) grade reduction: the office-grade a 1954 (Divino Afflatu)
 * feast carries under the 1955 interim rubrics. The S.R.C. General Decree of 23 March 1955
 * (in force 1 Jan 1956) abolished the semidouble grade (Title II.1); semidouble feasts are
 * kept as SIMPLES (II.20), and simple feasts are reduced to a bare COMMEMORATION without
 * historical lesson (II.21). Doubles of every class are unchanged, and a retained vigil keeps
 * its vigil grade. So the 1955 sanctoral is a pure function of the 1954 grades.
 */
const CUM_NOSTRA_1955_GRADE = {
  'duplex-i-classis': 'duplex-i-classis',
  'duplex-ii-classis': 'duplex-ii-classis',
  'duplex-maius': 'duplex-maius',
  duplex: 'duplex',
  semiduplex: 'simplex', // II.20 — the semidouble grade is suppressed
  simplex: 'commemoratio', // II.21 — simples reduced to a commemoration
  vigilia: 'vigilia',
  commemoratio: 'commemoratio',
};

/**
 * The four SANCTORAL vigils the 1955 reform retained (Title II.9, the "common vigils" on
 * fixed sanctoral dates): the Assumption, St John the Baptist, Ss Peter & Paul, and St
 * Lawrence. The reform's other retained vigils — Christmas and Pentecost (privileged, II.8)
 * and the Ascension (common) — are TEMPORAL, minted by the season-fillers, not carried here.
 * Every other vigil, including the nine 1954 apostles'/feast vigils and any in particular
 * calendars, is suppressed (they are exactly the `notInBaseEdition` 1954-only vigils).
 */
const CUM_NOSTRA_1955_RETAINED_VIGILS = new Set([
  'roman:sanctorale:assumptio:vigilia',
  'roman:sanctorale:ioannes-baptista:vigilia',
  'roman:sanctorale:petrus-paulus:vigilia',
  'roman:sanctorale:laurentius:vigilia',
]);

/**
 * Derive the 1955 (interim / Cum nostra hac aetate) edition from the 1954 (Divino Afflatu)
 * data: return the entries with a synthesised `roman-rubricae-1955` block on every entry the
 * 1955 rite keeps, so {@see transformSanctoraleEdition} fans it out exactly as an authored
 * diff would. The 1955 edition is a DERIVED delta — the reform is a mechanical transform of
 * the 1954 grades plus vigil suppression — so it is computed, never authored twice (issue
 * #69's "express as deltas to keep datasets maintainable"). Sanctoral OCTAVES are suppressed
 * wholesale (Title II.11) simply by the edition declaring none in octaves.yaml, so they need
 * no handling here.
 *
 * For each entry that realises 1954: the 1955 grade is the {@see CUM_NOSTRA_1955_GRADE} image
 * of the 1954 grade; colour, month, day, vigilOf, and any nameOverride are unchanged (the
 * reform moved no feast and changed no colour); the grade fact cites the 1955 decree
 * (`cn-1955`), the unchanged facts keep their 1954 cites. A suppressed vigil gets no block —
 * it is absent from 1955, exactly as it is authored absent from 1962.
 */
export function deriveCumNostra1955(entries) {
  const base = 'roman-divino-afflatu';
  const derived = 'roman-rubricae-1955';
  let retainedVigils = 0;
  const out = entries.map((entry) => {
    const ed = entry[base];
    if (!ed) {
      return entry; // not in 1954 → not in 1955
    }
    if (entry.kind === 'vigil') {
      if (!CUM_NOSTRA_1955_RETAINED_VIGILS.has(entry.id)) {
        return entry; // suppressed vigil → absent from 1955
      }
      retainedVigils += 1;
    }
    // Fail closed on a 1954 numeric-rank override: the derive carries the grade forward, but a
    // rankOverride pins the numeric RankClass off the default-for-grade — and the reform may
    // CHANGE the grade (semiduplex → simplex), so the 1954 override cannot be blindly forwarded.
    // Rather than silently drop the value (which would ship the default rank while a stale
    // cites.rankOverride mislabels its provenance), refuse until a maintainer handles it.
    if (ed.rankOverride !== undefined) {
      throw new Error(
        `deriveCumNostra1955: ${entry.id} carries a 1954 rankOverride; the 1955 derive does not forward ` +
          'it (the reform may change its grade). Author its 1955 numeric rank deliberately here.',
      );
    }
    const grade = CUM_NOSTRA_1955_GRADE[ed.legacyRank];
    if (grade === undefined) {
      throw new Error(
        `deriveCumNostra1955: no 1955 grade for 1954 legacyRank "${ed.legacyRank}" on ${entry.id}`,
      );
    }
    const block = {
      legacyRank: grade,
      colour: ed.colour,
      month: ed.month,
      day: ed.day,
      cites: { ...(ed.cites || {}), legacyRank: 'cn-1955' },
    };
    if (ed.vigilOf) {
      block.vigilOf = ed.vigilOf;
    }
    if (ed.nameOverride) {
      block.nameOverride = ed.nameOverride;
    }
    return { ...entry, [derived]: block };
  });
  if (retainedVigils !== CUM_NOSTRA_1955_RETAINED_VIGILS.size) {
    throw new Error(
      `deriveCumNostra1955: expected ${CUM_NOSTRA_1955_RETAINED_VIGILS.size} retained sanctoral vigils, ` +
        `found ${retainedVigils} — the 1954 vigil roster changed; re-verify against Cum nostra Title II.9`,
    );
  }
  return out;
}

/** Latin ordinal (feminine, agreeing with dies) for the 2nd..7th day within an octave. */
const OCTAVE_WITHIN_ORDINAL = {
  2: 'secunda',
  3: 'tertia',
  4: 'quarta',
  5: 'quinta',
  6: 'sexta',
  7: 'septima',
};

/**
 * The legacy office-grade the OCTAVE DAY (dies octava, the 8th day) carries, by octave
 * class under the pre-1955 rubrics: a common octave day is a greater double, a simple
 * octave day is a simple. Privileged (temporal) octaves are not materialised here.
 */
const OCTAVE_DAY_GRADE = { common: 'duplex-maius', simple: 'simplex' };

/**
 * The legacy office-grade a day WITHIN the octave (dies infra octavam) carries. Only a
 * COMMON octave has proper days within (each a semidouble); a SIMPLE octave keeps only
 * its octave day, with no office on the intervening days.
 */
const WITHIN_OCTAVE_GRADE = 'semiduplex';

/** Days in each civil month; February as 28 (see {@see addDays}). */
const DAYS_IN_MONTH = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

/**
 * The civil `{ month, day }` that falls `offset` days after (month, day), rolling over
 * month and (for a December octave) year boundaries. February is treated as 28 days:
 * no octave-bearing feast of the Roman calendar sits in late February, so an octave
 * window never spans the 28→29 bissextile doubling — the octave day is a fixed civil
 * date, year-independent. Pure arithmetic, so the build stays deterministic (no clock).
 */
function addDays(month, day, offset) {
  let m = month;
  let d = day + offset;
  while (d > DAYS_IN_MONTH[m - 1]) {
    d -= DAYS_IN_MONTH[m - 1];
    m = m === 12 ? 1 : m + 1;
  }
  return { month: m, day: d };
}

/**
 * Fan the SANCTORAL octaves declared for one edition into the identity / attributes /
 * placement records the corpus already understands — the safer-split design: a sanctoral
 * octave is a fixed-date, feast-anchored window that behaves exactly like a VIGIL, so it
 * is pure DATA, not a runtime engine (docs/design/rubric-system-model.md). Only COMMON
 * and SIMPLE octaves are materialised here; the privileged (temporal) octaves — Christmas,
 * Easter, Pentecost, and the 1954 additions — are minted by the temporal season-fillers,
 * the SAME code path 1962 uses, so 1962's golden fixture is untouched by construction.
 *
 * Each `decl` is `{ bearingFeast, class: common|simple, genitive, cites: { class, name },
 * octaveDay? }`. A COMMON octave yields six days within (dies secunda..septima, each a
 * semidouble) plus the octave day (dies octava, a greater double); a SIMPLE octave yields
 * the octave day only (a simple). Set `octaveDay: false` to emit the days within but SKIP
 * the octave day — for an octave whose eighth day is perpetually displaced by a higher
 * fixed feast in this edition and whose survival as a commemoration is not yet confirmed
 * (e.g. in 1954 the Assumption octave day is occupied by the Immaculate Heart; deferred to
 * the precedence engine + dataset burndown). The octave's DATE and COLOUR are DERIVED from the bearing feast's own
 * edition block (offset by day count; the feast's colour by rubric) so they can never
 * disagree, and its numeric `rank` is DERIVED from the office-grade exactly as a feast's
 * is ({@see DEFAULT_RANK_BY_LEGACY}). The octave day's Latin title comes from the Missal
 * ("In Octava <genitive>"); each within-day is "Dies <ordinal> infra Octavam <genitive>".
 *
 * Returns `{ identity, attributes, placement }`. Identity is edition-INVARIANT (the Octave
 * Day of the Assumption is the same observance in whichever edition keeps octaves), so the
 * caller merges it, deduped, into the shared identity; attributes and placement are the
 * edition's own diff. `octaveOf` names the bearing feast, mirroring `vigilOf`.
 */
export function transformOctaves(entries, editionDir, decls) {
  const byId = new Map(entries.map((entry) => [entry.id, entry]));
  const identity = [];
  const attributes = [];
  const placement = [];

  for (const decl of decls) {
    const bearing = byId.get(decl.bearingFeast);
    if (!bearing) {
      throw new Error(`octave: bearing feast "${decl.bearingFeast}" is not a sanctoral entry`);
    }
    if (decl.class !== 'common' && decl.class !== 'simple') {
      throw new Error(
        `octave of "${decl.bearingFeast}": unknown class "${decl.class}" (expected "common" or "simple"; ` +
          `privileged/temporal octaves are minted by the temporal fillers, not materialised here)`,
      );
    }
    if (decl.class === 'simple' && decl.octaveDay === false) {
      throw new Error(
        `octave of "${decl.bearingFeast}": a simple octave with octaveDay:false materialises nothing ` +
          `(no days within, no octave day) — omit the declaration instead`,
      );
    }
    const feast = bearing[editionDir];
    if (!feast) {
      throw new Error(
        `octave of "${decl.bearingFeast}": the feast has no "${editionDir}" block, so the octave ` +
          `cannot inherit its date and colour`,
      );
    }
    if (feast.month === 2 && feast.day + 7 > 28) {
      throw new Error(
        `octave of "${decl.bearingFeast}": a late-February octave window would cross the 28→29 bissextile ` +
          `doubling, which the fixed-civil-date octave model cannot represent (see addDays)`,
      );
    }
    const declCites = decl.cites || {};
    if (declCites.class === undefined || declCites.name === undefined) {
      throw new Error(`octave of "${decl.bearingFeast}": cites must carry both "class" and "name"`);
    }
    if (typeof decl.genitive !== 'string' || decl.genitive.length === 0) {
      throw new Error(`octave of "${decl.bearingFeast}": a Latin "genitive" title phrase is required`);
    }
    const colourCite = (feast.cites || {}).colour;
    if (colourCite === undefined) {
      throw new Error(
        `octave of "${decl.bearingFeast}": the feast's colour is uncited, so the octave's colour cannot be derived`,
      );
    }

    const emit = (idSuffix, kind, dayOffset, name, grade) => {
      const id = `${decl.bearingFeast}:${idSuffix}`;
      const { month, day } = addDays(feast.month, feast.day, dayOffset);
      identity.push({
        id,
        kind,
        titulars: bearing.titulars,
        names: { la: name },
        cites: { 'names.la': declCites.name },
      });
      attributes.push({
        id,
        rank: DEFAULT_RANK_BY_LEGACY[grade],
        legacyRank: grade,
        colour: colourOf(feast.colour),
        cites: { colour: colourCite, legacyRank: declCites.class, rank: declCites.class },
      });
      placement.push({
        id,
        month,
        day,
        octaveOf: decl.bearingFeast,
        cites: { month: declCites.class, day: declCites.class },
      });
    };

    // A common octave keeps the six days within (dies secunda..septima); a simple octave
    // keeps only the octave day. Then, for both, the octave day (dies octava, +7 days) —
    // unless it is explicitly deferred (octaveDay: false) as perpetually displaced.
    if (decl.class === 'common') {
      for (let n = 2; n <= 7; n += 1) {
        emit(
          `infra-octavam:${n}`,
          'within-octave',
          n - 1,
          `Dies ${OCTAVE_WITHIN_ORDINAL[n]} infra Octavam ${decl.genitive}`,
          WITHIN_OCTAVE_GRADE,
        );
      }
    }
    if (decl.octaveDay !== false) {
      emit('in-octava', 'octave-day', 7, `In Octava ${decl.genitive}`, OCTAVE_DAY_GRADE[decl.class]);
    }
  }

  return { identity, attributes, placement };
}

/**
 * Fan a particular-calendar overlay's YAML into its NDJSON operation rows, the
 * metadata singleton, and the born-cited provenance shadow records (Core #76).
 *
 * Each operation is one row; the three kinds mirror the PHP {@see OverlayOperation}
 * value objects — `rerank` (change an existing feast's rank, optionally its colour),
 * `add` (a proper feast the universal calendar lacks, carrying a full entry), and
 * `suppress` (drop a universal feast). Rows are sorted by their target observance id,
 * so the file is deterministic and the order it is written in matches the
 * order-independent order the decorator applies them. The `provenance` records feed
 * the same born-cited gate the sanctoral uses: every cite must resolve to a registered
 * source, and an added feast's title must cite a public-domain text source.
 */
export function transformOverlay(overlay) {
  const rows = [];
  const provenance = [];

  for (const op of overlay.operations || []) {
    if (op.op === 'rerank') {
      const cites = op.cites || {};
      const row = { op: 'rerank', target: op.target, rank: op.rank, cites };
      if (op.colour !== undefined) {
        row.colour = colourOf(op.colour);
      }
      rows.push(row);
      provenance.push({ id: op.target, cites });
    } else if (op.op === 'suppress') {
      const cites = op.cites || {};
      rows.push({ op: 'suppress', target: op.target, cites });
      provenance.push({ id: op.target, cites });
    } else if (op.op === 'add') {
      const e = op.entry;
      const cites = e.cites || {};
      const entry = {
        id: e.id,
        kind: e.kind,
        titulars: e.titulars,
        names: e.names,
        rank: e.rank,
        colour: colourOf(e.colour),
        month: e.month,
        day: e.day,
        cites,
      };
      if (e.vigilOf) {
        entry.vigilOf = e.vigilOf;
      }
      rows.push({ op: 'add', entry });
      provenance.push({ id: e.id, cites, names: e.names });
    } else {
      throw new Error(`overlay ${overlay.id}: unknown operation "${String(op.op)}"`);
    }
  }

  const targetOf = (row) => (row.op === 'add' ? row.entry.id : row.target);
  rows.sort((a, b) => {
    const ka = targetOf(a);
    const kb = targetOf(b);
    return ka < kb ? -1 : ka > kb ? 1 : 0;
  });

  return {
    meta: { id: overlay.id, name: overlay.name, rite: overlay.rite, operations: rows.length },
    rows,
    provenance,
  };
}

/** Sort a list of rows by a string key in code-unit order (stable, explicit). */
function byKey(key) {
  return (a, b) => (a[key] < b[key] ? -1 : a[key] > b[key] ? 1 : 0);
}

/**
 * Fan the temporal archetypes into the two schema'd shapes, joined by the
 * `archetype` key: the edition-invariant identity (kind + the Latin name
 * template) and the per-edition attributes (rank + colour). Mirrors the sanctoral
 * identity/attributes split. `names.la` is a template the PHP fillers render.
 */
export function transformTemporale(archetypes, edition) {
  const identity = [];
  const attributes = [];

  for (const a of archetypes) {
    const cites = a.cites || {};

    identity.push({
      archetype: a.key,
      kind: a.kind,
      names: { la: a.name },
      cites: { 'names.la': cites.name },
    });

    attributes.push({
      archetype: a.key,
      rank: a.rank,
      colour: colourOf(a.colour),
      cites: { rank: cites.rank, colour: cites.colour },
    });
  }

  identity.sort(byKey('archetype'));
  attributes.sort(byKey('archetype'));

  return { identity, attributes, edition };
}

/**
 * Fan the precedence facts into the two frozen shapes for one edition: the
 * Table-of-Liturgical-Days tiers (`{ selector, line, ordinal, subOrder, cite }`,
 * sorted by ordinal) and the rules — the membership id-sets and the commemoration
 * limits, both variants of the precedence-rules oneOf, in a stable order.
 */
export function transformPrecedence(facts, edition) {
  const tiers = (facts.tiers || [])
    .map((t) => ({
      selector: t.selector,
      line: t.line,
      ordinal: t.ordinal,
      subOrder: t.subOrder,
      cite: t.cite,
    }))
    .sort((a, b) => a.ordinal - b.ordinal);

  const membership = (facts.membership || []).map((m) => ({
    rule: 'membership',
    name: m.name,
    ids: m.ids,
    cite: m.cite,
  }));
  const limits = (facts.commemorationLimits || []).map((c) => ({
    rule: 'commemoration-limit',
    dayClass: c.dayClass,
    limit: c.limit,
    cite: c.cite,
  }));
  const ruleKey = (r) => r.rule + ':' + (r.name ?? r.dayClass);
  const rules = [...limits, ...membership].sort((a, b) => {
    const ka = ruleKey(a);
    const kb = ruleKey(b);
    return ka < kb ? -1 : ka > kb ? 1 : 0;
  });

  return { tiers, rules, edition };
}

/**
 * Fan the temporal-skeleton facts into the two edition-invariant NDJSON shapes:
 * the Easter offsets (`{ slot, offset, cite }`) and the block->season assignments
 * (`{ block, season, cite }`). Both validate against the temporal-skeleton schema.
 */
export function transformTemporalSkeleton(facts) {
  const offsets = (facts.easterOffsets || [])
    .map((r) => ({ slot: r.slot, offset: r.offset, cite: r.cite }))
    .sort(byKey('slot'));
  const blockSeasons = (facts.blockSeasons || [])
    .map((r) => ({ block: r.block, season: r.season, cite: r.cite }))
    .sort(byKey('block'));

  return { offsets, blockSeasons };
}
