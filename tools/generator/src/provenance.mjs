// Born-cited provenance gate. Turns the clean-room policy into an enforced,
// fail-closed invariant: every human-readable string in the corpus must carry a
// citation, every citation must resolve to a registered source, and a transcribed
// title (names.*, nameOverride.*) must come from a public-domain `text` source —
// never a `reference` or `oracle` source. Facts (rank, colour, month, day) may
// cite any registered source, because facts are uncopyrightable.
//
// One narrow exception (Core option A, #108): a TEMPORAL day-label — a `names.la`
// on an `identity.temporale` row — is a generic, uncopyrightable descriptor
// ("Dominica per annum"), not a literary title, so it may cite either a
// public-domain text OR a `reference` source (the norming document that
// establishes it, e.g. the 1969 Normae universales). A sanctoral saint's title
// keeps the strict PD-text rule, and an `oracle` source is never a data source in
// either case. See docs/design/text-licensing-model.md.

/** The record fields whose values are transcribed, human-readable strings. */
const NAME_GROUPS = [
  ['names', 'names.'],
  ['nameOverride', 'nameOverride.'],
];

/** A citation value may carry a locator (`key:locator`); the source key is the head. */
function sourceKeyOf(ref) {
  return String(ref).split(':')[0];
}

/**
 * Check provenance across every shape. `shapes` is a list of
 * `{ shape, records }`. Returns `{ problems, usage }`: `problems` is a list of
 * fail-closed violations (empty when clean); `usage` is a Map of source key to
 * citation count.
 */
export function checkProvenance(shapes, sources) {
  const byKey = new Map(sources.map((source) => [source.key, source]));
  const problems = [];
  const usage = new Map();

  for (const { shape, records } of shapes) {
    for (const record of records) {
      const cites = record.cites || {};
      const rowId = record.id ?? record.archetype ?? '?';

      // Every citation must resolve; a name citation must be a PD text source.
      for (const [field, ref] of Object.entries(cites)) {
        const key = sourceKeyOf(ref);
        usage.set(key, (usage.get(key) || 0) + 1);
        const source = byKey.get(key);
        if (!source) {
          problems.push(`${shape} ${rowId}: field "${field}" cites unknown source "${key}"`);
          continue;
        }
        const isName = field.startsWith('names.') || field.startsWith('nameOverride.');
        if (isName) {
          const pdText = source.kind === 'text' && source.rights === 'public-domain';
          // A temporal day-label may also cite a `reference` source (option A): it is a
          // generic descriptor, not a transcribed literary title. Sanctoral titles stay
          // strict; an oracle is never admissible as a name source in either case.
          const temporalLabelRef = shape === 'identity.temporale' && source.kind === 'reference';
          if (!pdText && !temporalLabelRef) {
            problems.push(
              `${shape} ${rowId}: "${field}" transcribes from "${key}" ` +
                `(kind=${source.kind}, rights=${source.rights}); ` +
                (shape === 'identity.temporale'
                  ? 'a temporal label must cite a public-domain text or a reference source'
                  : 'a title must cite a public-domain text source'),
            );
          }
        }
      }

      // Every human-readable string must carry a citation.
      for (const [group, prefix] of NAME_GROUPS) {
        const strings = record[group];
        if (!strings) {
          continue;
        }
        for (const locale of Object.keys(strings)) {
          if (cites[prefix + locale] === undefined) {
            problems.push(`${shape} ${rowId}: string "${prefix}${locale}" has no citation`);
          }
        }
      }
    }
  }

  return { problems, usage };
}

/**
 * The used-sources report for the manifest: every registered source that at least
 * one datum cites, with its rights and a usage count, sorted by key.
 */
export function usedSources(sources, usage) {
  return sources
    .filter((source) => usage.has(source.key))
    .map((source) => ({
      key: source.key,
      urn: source.urn,
      kind: source.kind,
      rights: source.rights,
      title: source.title,
      uses: usage.get(source.key),
    }))
    .sort((a, b) => (a.key < b.key ? -1 : a.key > b.key ? 1 : 0));
}
