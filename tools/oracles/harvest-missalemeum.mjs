// Harvest a pinned missalemeum calendar fixture for the validation harness (#45/#47).
//
// missalemeum (https://www.missalemeum.com, github.com/mmolenda/missalemeum, MIT,
// (c) 2022 Marcin Molenda) is an independent implementation of the 1962 (Rubricae
// 1960) calendar. Its public API returns, per day, the resolved office's title,
// class (rank 1..4), liturgical colour(s), and commemorations. Those are calendar
// *facts*; harvesting them to cross-check our engine is clean-room-safe, and the
// upstream code is MIT-licensed. We store only the fields we compare (plus the
// English title, for human-readable divergence reports).
//
// This is a MAINTAINER tool, run offline to refresh the committed fixture; the
// validation test reads the committed files and never touches the network. Re-run:
//   node tools/oracles/harvest-missalemeum.mjs [firstYear] [lastYear]
// Defaults to 2024..2026.

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(HERE, '..', '..', 'tests', 'Validation', 'fixtures', 'missalemeum');
const API = (year) => `https://www.missalemeum.com/en/api/v5/calendar/${year}`;
const SOURCE = {
  name: 'missalemeum',
  homepage: 'https://www.missalemeum.com',
  repository: 'https://github.com/mmolenda/missalemeum',
  license: 'MIT',
  apiVersion: 'v5',
};

/** Reduce one upstream day to the comparable, self-describing fixture record. */
function normalise(entry) {
  return {
    date: entry.id,
    title: entry.title,
    rank: entry.rank,
    colors: entry.colors,
    commemorations: entry.commemorations.length,
  };
}

async function harvestYear(year) {
  const res = await fetch(API(year));
  if (!res.ok) {
    throw new Error(`missalemeum ${year}: HTTP ${res.status}`);
  }
  const days = await res.json();
  return days
    .map(normalise)
    .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
}

async function main(firstYear, lastYear, harvestedOn) {
  mkdirSync(OUT_DIR, { recursive: true });
  const years = [];
  for (let year = firstYear; year <= lastYear; year++) {
    const rows = await harvestYear(year);
    const ndjson = rows.map((r) => JSON.stringify(r)).join('\n') + '\n';
    writeFileSync(join(OUT_DIR, `${year}.ndjson`), ndjson);
    years.push({ year, days: rows.length });
    process.stdout.write(`  ${year}: ${rows.length} days\n`);
  }

  const provenance = {
    source: SOURCE,
    harvestedOn,
    range: { firstYear, lastYear },
    files: years,
    fields: {
      date: 'ISO date (YYYY-MM-DD)',
      title: "the office's English title (for readable divergence reports; not compared)",
      rank: 'liturgical class, 1 (highest) .. 4',
      colors: "liturgical colour codes: w white, r red, g green, v violet, b black, p rose",
      commemorations: 'count of commemorations on the day',
    },
  };
  writeFileSync(join(OUT_DIR, 'provenance.json'), JSON.stringify(provenance, null, 2) + '\n');
  process.stdout.write(`Wrote fixture + provenance to ${OUT_DIR}\n`);
}

const firstYear = Number(process.argv[2] ?? 2024);
const lastYear = Number(process.argv[3] ?? 2026);
// The harvest date is passed in (not read from the clock) so a re-run with the same
// upstream data and date reproduces byte-identical provenance.
const harvestedOn = process.argv[4] ?? '2026-07-02';
main(firstYear, lastYear, harvestedOn).catch((err) => {
  process.stderr.write(String(err.stack || err) + '\n');
  process.exit(1);
});
