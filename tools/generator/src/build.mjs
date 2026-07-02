// The corpus build pipeline: load cited YAML facts -> transform into schema'd
// records -> validate against the frozen JSON Schemas -> serialize canonically
// -> write NDJSON + a MANIFEST. Deterministic by construction (see canonical.mjs);
// the corpus version comes from the facts, never the clock.
//
// The output is committed to the repository. Consumers (the PHP engine) load the
// committed files and never run this generator — Node is build-time only.

import { mkdirSync, writeFileSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { loadYaml } from './facts.mjs';
import { makeValidators, validateAll } from './validate.mjs';
import {
  transformSanctorale,
  transformTemporalSkeleton,
  transformTemporale,
  transformPrecedence,
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
    ],
    sources,
  );

  // The temporal-skeleton and precedence rows carry no human-readable strings,
  // only a single `cite` per structural fact — still every cite must resolve to a
  // registered source (the clean-room rule), and its use is counted for the report.
  const sourceKeys = new Set(sources.map((source) => source.key));
  for (const row of [...offsets, ...blockSeasons, ...precedence.tiers, ...precedence.rules]) {
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
    generator: meta.generator || '@introibo/corpus-generator',
    license: 'CC0-1.0',
    editions: [edition],
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
