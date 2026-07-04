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
    const ed = entry[edition];
    if (!ed) {
      throw new Error(`entry ${entry.id} has no data for edition "${edition}"`);
    }

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
 * Fan a NON-BASE edition (Core v0.3.0: 1954 / 1955) out into its `{ attributes,
 * placement }` arrays — its diff from the base edition. Identity is edition-invariant
 * and already emitted by the base pass, so this emits only the per-edition Layer-2 and
 * placement shapes.
 *
 * An entry belongs to this edition iff it carries a block under `editionDir`; entries
 * without one are simply absent from the edition (a feast the reform instituted later, or
 * one this slice has not yet authored). The block states the edition's realization
 * explicitly and cited: its `rank` is the normalized {@see RankClass} ordinal and
 * `legacyRank` its native pre-1960 grade token (duplex/semiduplex/simplex…), both carried
 * so the 1954/1955 precedence engines can order the fine grades the four classes collapse.
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
    const attributeRecord = {
      id: entry.id,
      rank: ed.rank,
      colour: colourOf(ed.colour),
      cites: citesFor(
        edCites,
        (key) =>
          key === 'rank' || key === 'colour' || key === 'legacyRank' || key.startsWith('nameOverride.'),
      ),
    };
    if (ed.legacyRank) {
      attributeRecord.legacyRank = ed.legacyRank;
    }
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
