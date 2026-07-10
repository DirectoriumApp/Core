// Manifest integrity (#238). Verify that every file a corpus MANIFEST.json lists
// hashes to the sha256 the manifest records, and that none is missing. This is the
// check a dataset consumer runs against a download ("the checksum matches the
// committed manifest") and the integrity guarantee the citable release makes; the
// reproducibility gate (verify.mjs) proves the corpus is a fresh build, this proves
// the shipped manifest actually describes the shipped bytes.

import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { sha256 } from './canonical.mjs';

/**
 * Verify the corpus under `root` against its own MANIFEST.json. Returns a list of
 * human-readable problems; an empty list means the manifest describes the tree exactly.
 */
export function verifyManifest(root) {
  const manifestPath = join(root, 'MANIFEST.json');
  if (!existsSync(manifestPath)) {
    return [`MANIFEST.json: missing under ${root}`];
  }

  let manifest;
  try {
    manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  } catch (err) {
    return [`MANIFEST.json: not valid JSON (${err.message})`];
  }

  const files = manifest.files || {};
  const rels = Object.keys(files);
  if (rels.length === 0) {
    return ['MANIFEST.json: lists no files'];
  }

  const problems = [];
  for (const rel of rels) {
    const path = join(root, rel);
    if (!existsSync(path)) {
      problems.push(`${rel}: listed in the manifest but missing on disk`);
      continue;
    }
    const actual = sha256(readFileSync(path, 'utf8'));
    const expected = files[rel].sha256;
    if (actual !== expected) {
      problems.push(`${rel}: sha256 ${actual} does not match manifest ${expected}`);
    }
  }

  return problems;
}
