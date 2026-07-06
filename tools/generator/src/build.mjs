// The corpus build pipeline: load cited YAML facts -> transform into schema'd
// records -> validate against the frozen JSON Schemas -> serialize canonically
// -> write NDJSON + a MANIFEST. Deterministic by construction (see canonical.mjs);
// the corpus version comes from the facts, never the clock.
//
// The output is committed to the repository. Consumers (the PHP engine) load the
// committed files and never run this generator — Node is build-time only.

import { mkdirSync, writeFileSync, copyFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { loadYaml } from './facts.mjs';
import { makeValidators, validateAll } from './validate.mjs';
import {
  transformSanctorale,
  transformSanctoraleEdition,
  transformOctaves,
  transformTemporalSkeleton,
  transformTemporale,
  transformPrecedence,
  transformOverlay,
  checkCoPlacement,
} from './transform.mjs';
import { checkProvenance, usedSources } from './provenance.mjs';
import { toNdjson, toPretty, sha256 } from './canonical.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
export const GENERATOR_DIR = join(HERE, '..');
export const CORE_DIR = join(GENERATOR_DIR, '..', '..');
export const SCHEMA_DIR = join(CORE_DIR, 'data', 'corpus', 'schema');
export const FACTS_DIR = join(GENERATOR_DIR, 'facts');
export const TEMPLATES_DIR = join(GENERATOR_DIR, 'templates');
export const DEFAULT_OUT = join(CORE_DIR, 'data', 'corpus');

/** Sort records by their `id` primary key in code-unit order (stable, explicit). */
function byId(a, b) {
  if (a.id < b.id) {
    return -1;
  }
  return a.id > b.id ? 1 : 0;
}

/**
 * Discover and transform every particular-calendar overlay in facts/overlays
 * (Core #76), one per `<slug>.yaml`, slug-sorted for a deterministic build. Returns
 * `{ slug, meta, rows, provenance }` per overlay; an empty list when the directory
 * is absent, so the base corpus builds unchanged before any overlay exists.
 */
function loadOverlays() {
  const dir = join(FACTS_DIR, 'overlays');
  if (!existsSync(dir)) {
    return [];
  }
  const slugs = readdirSync(dir)
    .filter((file) => file.endsWith('.yaml'))
    .map((file) => file.replace(/\.yaml$/, ''))
    .sort();

  return slugs.map((slug) => ({ slug, ...transformOverlay(loadYaml(join(dir, slug + '.yaml'))) }));
}

/**
 * Build the corpus into `outDir` (defaults to the engine's data/corpus).
 * Returns the sorted list of generated file paths (relative to `outDir`).
 * Throws if any record fails schema validation — the build fails closed.
 */
export function build(outDir = DEFAULT_OUT) {
  const meta = loadYaml(join(FACTS_DIR, 'meta.yaml'));
  const edition = meta.edition;
  const entries = loadYaml(join(FACTS_DIR, 'sanctorale.yaml'));

  const sources = loadYaml(join(FACTS_DIR, 'sources.yaml')).sort((a, b) =>
    a.key < b.key ? -1 : a.key > b.key ? 1 : 0,
  );

  const { identity, attributes, placement } = transformSanctorale(entries, edition);
  identity.sort(byId);
  attributes.sort(byId);
  placement.sort(byId);

  const { offsets, blockSeasons } = transformTemporalSkeleton(
    loadYaml(join(FACTS_DIR, 'temporal-skeleton.yaml')),
  );

  const temporale = transformTemporale(
    loadYaml(join(FACTS_DIR, 'temporale.yaml')).archetypes,
    edition,
  );

  const precedence = transformPrecedence(loadYaml(join(FACTS_DIR, 'precedence.yaml')), edition);

  const overlays = loadOverlays();

  // Additional editions (Core v0.3.0: 1954, later 1955), each authored as a diff from a
  // base edition and materialised into its own dir. Only the per-edition Layer-2 and
  // placement shapes differ; identity and the temporal skeleton are edition-invariant and
  // emitted once above. The base pass is left untouched, so no additional edition can move
  // the base (1962) corpus — the golden fixture proves it.
  //
  // Each edition also materialises its SANCTORAL OCTAVES (#65) — declared per edition in
  // octaves.yaml, expanded into the same three shapes exactly as vigils are (the safer-split
  // design). Their attributes and placement join the edition's diff; their identity is
  // edition-invariant and is merged, deduped, into the shared identity below.
  const octavesByEdition = existsSync(join(FACTS_DIR, 'octaves.yaml'))
    ? loadYaml(join(FACTS_DIR, 'octaves.yaml'))
    : {};
  const extraEditions = (meta.editions || []).map((e) => {
    const base = transformSanctoraleEdition(entries, e.dir);
    const octaves = transformOctaves(entries, e.dir, octavesByEdition[e.dir] || []);
    const attributes = [...base.attributes, ...octaves.attributes].sort(byId);
    const placement = [...base.placement, ...octaves.placement].sort(byId);
    // A non-base edition may carry its OWN precedence table (Core v0.3.0: the pre-1955
    // Tabella Occurrentiae is a wholly different order, not a diff of the 1962 n.91 table),
    // authored whole in facts/editions/<dir>/precedence.yaml and read at runtime by that
    // edition's Rubrics19XXPrecedence. Absent the file the edition emits no precedence — its
    // engine must then be a 1962 variant reusing the base table, or it fails closed at load.
    const precedenceFile = join(FACTS_DIR, 'editions', e.dir, 'precedence.yaml');
    const precedence = existsSync(precedenceFile)
      ? transformPrecedence(loadYaml(precedenceFile), e.dir)
      : null;
    return { dir: e.dir, base: e.base, attributes, placement, octaveIdentity: octaves.identity, precedence };
  });

  // Octave identities are edition-invariant (the Octave Day of the Assumption is the same
  // observance in whichever edition keeps octaves), so the shared identity becomes the
  // cross-edition UNION of observances — each edition selecting what it observes via its own
  // placement. The 1960 edition places no octave, so the union grows but 1960 resolution
  // (and the golden fixture) is unmoved. Deduped by id so a later edition adding the same
  // octave does not double-list it — and if two editions declare the SAME octave id with a
  // DIFFERENT identity (e.g. a diverging title), that is an authoring error, not a silent
  // first-wins: fail closed.
  const identityById = new Map(identity.map((record) => [record.id, record]));
  for (const ed of extraEditions) {
    for (const record of ed.octaveIdentity) {
      const existing = identityById.get(record.id);
      if (existing === undefined) {
        identityById.set(record.id, record);
        identity.push(record);
      } else if (JSON.stringify(existing) !== JSON.stringify(record)) {
        throw new Error(
          `octave identity "${record.id}" differs between editions; a shared octave identity must be ` +
            'identical across editions (it is edition-invariant)',
        );
      }
    }
  }
  identity.sort(byId);

  // Orphan-identity gate: the shared identity is the cross-edition UNION, but every identity
  // must be PLACED by at least one edition. An identity no edition places is a dangling record
  // — a notInBaseEdition entry whose extra-edition block was mistyped, or a stray identity —
  // which would silently pass per-edition referential integrity. Fail closed.
  const placedIds = new Set(placement.map((record) => record.id));
  for (const ed of extraEditions) {
    for (const record of ed.placement) {
      placedIds.add(record.id);
    }
  }
  const orphans = identity.map((record) => record.id).filter((id) => !placedIds.has(id));
  if (orphans.length > 0) {
    throw new Error(
      'Sanctoral identity records placed by no edition (orphans): ' +
        orphans.join(', ') +
        '. Every identity must be placed by at least one edition — check for a mistyped edition block key.',
    );
  }

  // Co-placement gate (#64): every vigilOf/octaveOf target is placed in its own edition.
  checkCoPlacement([
    { dir: edition, placement },
    ...extraEditions.map((ed) => ({ dir: ed.dir, placement: ed.placement })),
  ]);

  const validators = makeValidators(SCHEMA_DIR);
  const errors = [
    ...validateAll(validators['identity.sanctorale'], identity, 'identity.sanctorale'),
    ...validateAll(validators['attributes.sanctorale'], attributes, 'attributes.sanctorale'),
    ...validateAll(validators['placement.sanctorale'], placement, 'placement.sanctorale'),
    ...validateAll(validators['temporal-skeleton'], offsets, 'temporal-skeleton'),
    ...validateAll(validators['temporal-skeleton'], blockSeasons, 'temporal-skeleton'),
    ...validateAll(validators['identity.temporale'], temporale.identity, 'identity.temporale'),
    ...validateAll(validators['attributes.temporale'], temporale.attributes, 'attributes.temporale'),
    ...validateAll(validators['precedence-tier'], precedence.tiers, 'precedence-tier'),
    ...validateAll(validators['precedence-rules'], precedence.rules, 'precedence-rules'),
    ...validateAll(validators['source'], sources, 'source'),
    ...overlays.flatMap((o) => validateAll(validators['overlay-operation'], o.rows, `overlay:${o.slug}`)),
    ...overlays.flatMap((o) => validateAll(validators['overlay'], [o.meta], `overlay-meta:${o.slug}`)),
    ...extraEditions.flatMap((ed) => [
      ...validateAll(validators['attributes.sanctorale'], ed.attributes, `attributes.sanctorale:${ed.dir}`),
      ...validateAll(validators['placement.sanctorale'], ed.placement, `placement.sanctorale:${ed.dir}`),
      ...(ed.precedence
        ? [
            ...validateAll(validators['precedence-tier'], ed.precedence.tiers, `precedence-tier:${ed.dir}`),
            ...validateAll(validators['precedence-rules'], ed.precedence.rules, `precedence-rules:${ed.dir}`),
          ]
        : []),
    ]),
  ];
  if (errors.length > 0) {
    throw new Error('Corpus schema validation failed:\n  ' + errors.join('\n  '));
  }

  // Born-cited provenance gate: fail closed on any uncited string, dangling
  // source key, or title transcribed from a non-public-domain source.
  const { problems, usage } = checkProvenance(
    [
      { shape: 'identity.sanctorale', records: identity },
      { shape: 'attributes.sanctorale', records: attributes },
      { shape: 'placement.sanctorale', records: placement },
      { shape: 'identity.temporale', records: temporale.identity },
      { shape: 'attributes.temporale', records: temporale.attributes },
      // Overlay operations carry their own cites: a rerank/suppress cites the
      // particular calendar's authority for the changed fact, an add carries a full
      // entry whose title must cite a public-domain text source. Same born-cited gate.
      ...overlays.map((o) => ({ shape: `overlay:${o.slug}`, records: o.provenance })),
      ...extraEditions.flatMap((ed) => [
        { shape: `attributes.sanctorale:${ed.dir}`, records: ed.attributes },
        { shape: `placement.sanctorale:${ed.dir}`, records: ed.placement },
      ]),
    ],
    sources,
  );

  // The temporal-skeleton and precedence rows carry no human-readable strings,
  // only a single `cite` per structural fact — still every cite must resolve to a
  // registered source (the clean-room rule), and its use is counted for the report.
  const sourceKeys = new Set(sources.map((source) => source.key));
  const extraPrecedenceRows = extraEditions.flatMap((ed) =>
    ed.precedence ? [...ed.precedence.tiers, ...ed.precedence.rules] : [],
  );
  for (const row of [...offsets, ...blockSeasons, ...precedence.tiers, ...precedence.rules, ...extraPrecedenceRows]) {
    const key = String(row.cite).split(':')[0];
    usage.set(key, (usage.get(key) || 0) + 1);
    if (!sourceKeys.has(key)) {
      const id = row.slot ?? row.block ?? row.selector ?? row.name ?? row.dayClass;
      problems.push(`fact row ${id}: cites unknown source "${key}"`);
    }
  }

  if (problems.length > 0) {
    throw new Error('Corpus provenance gate failed:\n  ' + problems.join('\n  '));
  }

  // Declared primary sort key per file; records were sorted above.
  const outputs = {
    'identity/sanctorale.ndjson': { text: toNdjson(identity), records: identity.length, primaryKey: 'id' },
    [`editions/${edition}/attributes.sanctorale.ndjson`]: {
      text: toNdjson(attributes),
      records: attributes.length,
      primaryKey: 'id',
    },
    [`editions/${edition}/placement.sanctorale.ndjson`]: {
      text: toNdjson(placement),
      records: placement.length,
      primaryKey: 'id',
    },
    'temporal/easter-offsets.ndjson': { text: toNdjson(offsets), records: offsets.length, primaryKey: 'slot' },
    'temporal/block-seasons.ndjson': {
      text: toNdjson(blockSeasons),
      records: blockSeasons.length,
      primaryKey: 'block',
    },
    'identity/temporale.ndjson': {
      text: toNdjson(temporale.identity),
      records: temporale.identity.length,
      primaryKey: 'archetype',
    },
    [`editions/${edition}/attributes.temporale.ndjson`]: {
      text: toNdjson(temporale.attributes),
      records: temporale.attributes.length,
      primaryKey: 'archetype',
    },
    [`editions/${edition}/precedence-tiers.ndjson`]: {
      text: toNdjson(precedence.tiers),
      records: precedence.tiers.length,
      primaryKey: 'selector',
    },
    [`editions/${edition}/precedence-rules.ndjson`]: {
      text: toNdjson(precedence.rules),
      records: precedence.rules.length,
      primaryKey: 'rule',
    },
    'sources.ndjson': { text: toNdjson(sources), records: sources.length, primaryKey: 'key' },
  };

  // Each particular-calendar overlay contributes its operations (one NDJSON row per
  // operation, sorted by target) plus a metadata singleton naming its URN and rite.
  for (const o of overlays) {
    outputs[`overlays/${o.slug}/operations.ndjson`] = {
      text: toNdjson(o.rows),
      records: o.rows.length,
      primaryKey: 'target',
    };
    outputs[`overlays/${o.slug}/overlay.json`] = {
      text: toPretty(o.meta),
      records: 1,
      primaryKey: 'id',
    };
  }

  // Each additional edition contributes its diff — the per-edition Layer-2 attributes and
  // the placement — into its own dir. Identity and the temporal skeleton are shared.
  for (const ed of extraEditions) {
    outputs[`editions/${ed.dir}/attributes.sanctorale.ndjson`] = {
      text: toNdjson(ed.attributes),
      records: ed.attributes.length,
      primaryKey: 'id',
    };
    outputs[`editions/${ed.dir}/placement.sanctorale.ndjson`] = {
      text: toNdjson(ed.placement),
      records: ed.placement.length,
      primaryKey: 'id',
    };
    if (ed.precedence) {
      outputs[`editions/${ed.dir}/precedence-tiers.ndjson`] = {
        text: toNdjson(ed.precedence.tiers),
        records: ed.precedence.tiers.length,
        primaryKey: 'selector',
      };
      outputs[`editions/${ed.dir}/precedence-rules.ndjson`] = {
        text: toNdjson(ed.precedence.rules),
        records: ed.precedence.rules.length,
        primaryKey: 'rule',
      };
    }
  }

  const relPaths = Object.keys(outputs).sort();
  for (const rel of relPaths) {
    const dest = join(outDir, rel);
    mkdirSync(dirname(dest), { recursive: true });
    writeFileSync(dest, outputs[rel].text);
  }

  // Stamp the dataset CC0 by copying the canonical licence text into the tree.
  const licenseDest = join(outDir, 'LICENSE.txt');
  mkdirSync(dirname(licenseDest), { recursive: true });
  copyFileSync(join(TEMPLATES_DIR, 'CC0-1.0.txt'), licenseDest);

  const files = {};
  for (const rel of relPaths) {
    files[rel] = {
      records: outputs[rel].records,
      primaryKey: outputs[rel].primaryKey,
      sha256: sha256(outputs[rel].text),
    };
  }
  const manifest = {
    corpusVersion: meta.corpusVersion,
    generator: meta.generator || '@directorium/corpus-generator',
    license: 'CC0-1.0',
    editions: [edition, ...extraEditions.map((ed) => ed.dir)],
    overlays: overlays.map((o) => o.slug),
    sources: usedSources(sources, usage),
    files,
  };
  writeFileSync(join(outDir, 'MANIFEST.json'), toPretty(manifest));

  return [...relPaths, 'MANIFEST.json', 'LICENSE.txt'].sort();
}

// CLI entry point (`npm run build`).
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const written = build();
  process.stdout.write('Generated corpus:\n');
  for (const rel of written) {
    process.stdout.write(`  ${rel}\n`);
  }
}
