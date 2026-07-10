// Release packaging (#239). Prepare the CC0 dataset for a versioned, citable release:
// verify the committed corpus against its manifest, then emit, into a dist directory,
// a SHA256SUMS file (standard `<sha256>  <path>` lines a downloader checks with
// `sha256sum -c`) and the release metadata. The archive is named with the data-version
// (the corpus's `corpusVersion`, #54); the release workflow assembles the tarball and
// attaches it, its `.sha256`, and SHA256SUMS to the GitHub release.

import { readFileSync, writeFileSync, mkdirSync, appendFileSync, readdirSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
import { sha256 } from './canonical.mjs';
import { DEFAULT_OUT } from './build.mjs';
import { verifyManifest } from './manifest.mjs';

/** The canonical, version-stamped archive name for a corpus release. */
export function datasetName(corpusVersion) {
  return `directorium-dataset-${corpusVersion}`;
}

/**
 * Every file under `root`, as POSIX paths relative to `root`, sorted. This is exactly
 * what the release tarball archives, so SHA256SUMS built from it covers the whole
 * artifact — including the CC0 schema files the manifest does not itself list.
 */
function listFiles(root, dir = root) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...listFiles(root, full));
    } else {
      out.push(relative(root, full).split(sep).join('/'));
    }
  }
  return out;
}

/**
 * Prepare a release of the corpus under `corpusRoot` into `distDir`. Throws if the
 * corpus does not match its manifest (a citable artifact must be self-consistent).
 * Returns `{ name, corpusVersion, fileCount }`.
 */
export function prepareRelease(distDir, corpusRoot = DEFAULT_OUT) {
  const problems = verifyManifest(corpusRoot);
  if (problems.length > 0) {
    throw new Error(`corpus does not match its manifest:\n  ${problems.join('\n  ')}`);
  }

  const manifest = JSON.parse(readFileSync(join(corpusRoot, 'MANIFEST.json'), 'utf8'));
  const corpusVersion = manifest.corpusVersion;
  const name = datasetName(corpusVersion);

  // Every file the tarball ships, so `sha256sum -c SHA256SUMS` covers the whole
  // archive — the generated data, its MANIFEST.json and CC0 LICENSE.txt, and the
  // schema files (which are CC0 dataset files the generator does not itself emit).
  const rels = listFiles(corpusRoot).sort();
  const sums = rels
    .map((rel) => `${sha256(readFileSync(join(corpusRoot, rel), 'utf8'))}  ${rel}`)
    .join('\n');

  mkdirSync(distDir, { recursive: true });
  writeFileSync(join(distDir, 'SHA256SUMS'), `${sums}\n`);
  writeFileSync(
    join(distDir, 'release-metadata.json'),
    `${JSON.stringify({ name, corpusVersion, fileCount: rels.length, license: 'CC0-1.0' }, null, 2)}\n`,
  );

  return { name, corpusVersion, fileCount: rels.length };
}

// CLI: `npm run package -- <distDir>` — prepares the dist directory and, in Actions,
// exposes the archive name to the release workflow via GITHUB_OUTPUT.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const distDir = process.argv[2] || join(process.cwd(), 'dist');
  const { name, corpusVersion, fileCount } = prepareRelease(distDir);
  process.stdout.write(
    `prepared ${name} (${fileCount} files, corpusVersion ${corpusVersion}) in ${distDir}\n`,
  );
  if (process.env.GITHUB_OUTPUT) {
    appendFileSync(process.env.GITHUB_OUTPUT, `dataset_name=${name}\n`);
  }
}
