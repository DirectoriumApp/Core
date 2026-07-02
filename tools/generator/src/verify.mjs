// Reproducibility gate. Runs two guarantees and exits non-zero on any difference:
//
//   1. determinism  — two fresh builds produce byte-identical output;
//   2. freshness    — the committed corpus matches a fresh build (nothing stale,
//                     no hand-edit that the generator would not reproduce).
//
// CI runs `npm run verify`; a non-empty diff is a red build.

import { mkdtempSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { build, DEFAULT_OUT } from './build.mjs';

/** Byte-compare `paths` between two roots; return a list of difference messages. */
function diff(expectedRoot, actualRoot, paths) {
  const problems = [];
  for (const rel of paths) {
    const actual = join(actualRoot, rel);
    if (!existsSync(actual)) {
      problems.push(`${rel}: missing`);
      continue;
    }
    if (!readFileSync(join(expectedRoot, rel)).equals(readFileSync(actual))) {
      problems.push(`${rel}: bytes differ`);
    }
  }
  return problems;
}

const tmpA = mkdtempSync(join(tmpdir(), 'introibo-corpus-a-'));
const tmpB = mkdtempSync(join(tmpdir(), 'introibo-corpus-b-'));
try {
  const pathsA = build(tmpA);
  const pathsB = build(tmpB);

  const problems = [];
  if (JSON.stringify(pathsA) !== JSON.stringify(pathsB)) {
    problems.push('determinism: the set of generated files differs between builds');
  }
  problems.push(...diff(tmpA, tmpB, pathsA).map((m) => `determinism: ${m}`));
  problems.push(...diff(tmpA, DEFAULT_OUT, pathsA).map((m) => `freshness: ${m}`));

  if (problems.length > 0) {
    process.stderr.write('CORPUS VERIFY FAILED:\n');
    for (const p of problems) {
      process.stderr.write(`  ${p}\n`);
    }
    process.stderr.write('\nRun `npm run build` and commit the result.\n');
    process.exit(1);
  }
  process.stdout.write(
    `corpus verify OK — ${pathsA.length} files, reproducible across builds and matching the committed copy.\n`,
  );
} finally {
  rmSync(tmpA, { recursive: true, force: true });
  rmSync(tmpB, { recursive: true, force: true });
}
