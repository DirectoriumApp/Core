// Harvest a pinned SSPX 1962 ordo fixture for the validation harness (#45/#49).
//
// The Society of St Pius X publishes its operative 1962 (Rubricae 1960) ordo as a
// free public web app at https://1962ordo.today, backed by a JSON endpoint that
// returns, per day, the resolved office's name, its class (I..IV, embedded in the
// German localisation), and a feast flag. Those are calendar *facts*; harvesting
// them to cross-check our engine is clean-room-safe (facts are not copyrightable),
// and we store only the fields we compare (plus the English name, for readable
// divergence reports).
//
// SSPX is a *particular* calendar: it keeps the universal 1962 base but adds its own
// observances (e.g. St Pius X and the Seven Sorrows elevated to I. class, tagged
// "(FSSPX)" upstream). So this oracle is not an authority on the base edition —
// missalemeum is — but a second independent witness that both corroborates base-1962
// rank findings and enumerates the SSPX-particular differences that seed the v0.2
// overlay worklist.
//
// This is a MAINTAINER tool, run offline to refresh the committed fixture; the
// validation test reads the committed files and never touches the network. Re-run:
//   node tools/oracles/harvest-sspx.mjs [firstYear] [lastYear] [harvestDate]
// Defaults to 2024..2026 (the range shared with the missalemeum fixture, so the two
// oracles can be cross-checked day for day). The endpoint offers 2017..2026.

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(HERE, '..', '..', 'tests', 'Validation', 'fixtures', 'sspx');
const API = 'https://1962ordo.today/get-liturgical-days/';
const SOURCE = {
  name: 'SSPX 1962 ordo',
  publisher: 'Society of St Pius X, District of the USA',
  homepage: 'https://1962ordo.today',
  endpoint: API,
  particularCalendar: true,
};

// The class (I..IV) is carried in the German localisation as "I. Klasse", tolerant of
// the upstream "Klase" typo and a missing period after the numeral. Longest numerals
// are tried first so "III" is never mis-read as "II".
const CLASS_RE = /\b(IV|III|II|I)\.?\s*Klas?se\b/;
const ROMAN = { I: 1, II: 2, III: 3, IV: 4 };

/** Reduce one upstream day to the comparable, self-describing fixture record. */
function normalise(entry) {
  const german = (entry.nameML && entry.nameML.DE) || '';
  const match = german.match(CLASS_RE);
  return {
    date: `${entry.date.slice(0, 4)}-${entry.date.slice(4, 6)}-${entry.date.slice(6, 8)}`,
    name: entry.name,
    klasse: match ? ROMAN[match[1]] : null,
    particular: /FSSPX/i.test(german) || /FSSPX/i.test(entry.name || ''),
    feast: Boolean(entry.feast),
  };
}

async function main(firstYear, lastYear, harvestedOn) {
  const res = await fetch(API);
  if (!res.ok) {
    throw new Error(`SSPX ordo: HTTP ${res.status}`);
  }
  const payload = await res.json();

  mkdirSync(OUT_DIR, { recursive: true });
  const years = [];
  for (let year = firstYear; year <= lastYear; year++) {
    const seen = new Set();
    const rows = payload.liturgicalDays
      .filter((d) => d.date.slice(0, 4) === String(year))
      .map(normalise)
      // The feed carries a few duplicate dates; keep the first, deterministically.
      .filter((r) => (seen.has(r.date) ? false : seen.add(r.date)))
      .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));

    const ndjson = rows.map((r) => JSON.stringify(r)).join('\n') + '\n';
    writeFileSync(join(OUT_DIR, `${year}.ndjson`), ndjson);
    const withClass = rows.filter((r) => r.klasse !== null).length;
    years.push({ year, days: rows.length, withClass });
    process.stdout.write(`  ${year}: ${rows.length} days (${withClass} with a parsed class)\n`);
  }

  const provenance = {
    source: SOURCE,
    harvestedOn,
    range: { firstYear, lastYear },
    files: years,
    fields: {
      date: 'ISO date (YYYY-MM-DD)',
      name: "the office's English name (for readable divergence reports; not compared)",
      klasse: 'liturgical class parsed from the German localisation, 1 (highest) .. 4, or null when absent upstream',
      particular: 'true when upstream tags the day "(FSSPX)" — an SSPX particular observance',
      feast: 'upstream feast flag',
    },
    notes:
      'SSPX is a particular calendar (universal 1962 base plus its own observances). ' +
      'Colour and commemoration counts are not exposed by the feed, so they are not compared. ' +
      'Class is compared where parsed; days without a parsed class are skipped (a documented allowance).',
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
