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

/** Sort a list of rows by a string key in code-unit order (stable, explicit). */
function byKey(key) {
  return (a, b) => (a[key] < b[key] ? -1 : a[key] > b[key] ? 1 : 0);
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
