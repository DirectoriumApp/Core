// Born-cited provenance gate. Turns the clean-room policy into an enforced,
// fail-closed invariant: every human-readable string in the corpus must carry a
// citation, every citation must resolve to a registered source, and a transcribed
// title (names.*, nameOverride.*) must come from a public-domain `text` source —
// never a `reference` or `oracle` source. Facts (rank, colour, month, day) may
// cite any registered source, because facts are uncopyrightable.
//
// One exception (Core option A, #108): a generic, uncopyrightable name-DESCRIPTOR
// — as opposed to a transcribed literary title — may cite either a public-domain
// text OR a `reference` source (the norming document that establishes it). This
// covers two record types:
//   * a TEMPORAL day-label on an `identity.temporale` row ("Dominica per annum"),
//     cited to the 1969 Normae universales; and
//   * a SANCTORAL descriptor on an `identity.sanctorale` row — a saint's proper name
//     plus a generic grade word ("S. Pii de Pietrelcina, presbyter") or a reform-coined
//     feast title ("In Praesentatione Domini") — for the reformed calendar's own saints
//     (canonised after the public-domain sources close, or re-titled by the reform),
//     cited to the promulgating General Roman Calendar (cal-rom-1969).
// Both are uncopyrightable identifiers, not the copyrighted Missal's running texts,
// which are never transcribed. An `oracle` source is never a name source in either
// case. See docs/design/text-licensing-model.md (option A) and
// docs/design/novus-ordo-calendar-model.md (the corpus).

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
          // A temporal day-label OR a sanctoral name-descriptor may also cite a `reference`
          // source (option A): a generic, uncopyrightable identifier, not a transcribed literary
          // title. An oracle is never admissible as a name source in either case.
          const descriptorShape = shape === 'identity.temporale' || shape === 'identity.sanctorale';
          const descriptorRef = descriptorShape && source.kind === 'reference';
          if (!pdText && !descriptorRef) {
            problems.push(
              `${shape} ${rowId}: "${field}" transcribes from "${key}" ` +
                `(kind=${source.kind}, rights=${source.rights}); ` +
                (descriptorShape
                  ? 'a name-descriptor must cite a public-domain text or a reference source'
                  : 'a name must cite a public-domain text source'),
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
