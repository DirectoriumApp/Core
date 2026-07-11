// Harvest the pinned Novus Ordo (2002) oracle fixtures for the validation harness
// (#45 / #260). The reformed General Roman Calendar is cross-checked against TWO
// independent, open-source calendar engines — the ≥2-oracle standard (roadmap v3):
//
//   * LitCal (https://litcal.johnromanodorazio.com, github.com/Liturgical-Calendar/LiturgicalCalendarAPI,
//     Apache-2.0, PHP) — John R. D'Orazio's Liturgical Calendar API. Returns a
//     whole civil year in one response; each event carries a numeric `grade`
//     (0 weekday .. 6 solemnity, 7 higher), liturgical colour(s), and a stable key.
//   * calapi / In Adiutorium (http://calapi.inadiutorium.cz, github.com/igneus/church-calendar-api,
//     LGPL-3.0-or-later, Ruby) — an independent engine by Jakub Pavlík. Per-day; each celebration
//     carries a `rank` string, a `rank_num`, and a colour, and it lists the day's
//     OPTIONAL memorials separately from the obligatory/ferial principal.
//
// Both emit calendar *facts* (which celebration, its grade, its colour) — harvesting
// them to cross-check our engine is clean-room-safe, and both codebases are permissively
// licensed. They are run-and-compare ORACLES ONLY, never a data source: nothing here
// feeds the corpus. We store the raw comparable fields (plus a title, for readable
// divergence reports); the PHP harness ({@see NovusOrdoOracle}) does the normalisation.
//
// This is a MAINTAINER tool, run offline to refresh the committed fixtures; the
// validation test reads the committed files and never touches the network. Re-run:
//   node tools/oracles/harvest-novus-ordo.mjs [firstYear] [lastYear] [harvestedOn]
// Defaults to 2025..2026.

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(HERE, '..', '..', 'tests', 'Validation', 'fixtures', 'novus-ordo');

const LITCAL = (year) =>
  `https://litcal.johnromanodorazio.com/api/dev/calendar?year=${year}&year_type=CIVIL&locale=la`;
const CALAPI = (year, month, day) =>
  `http://calapi.inadiutorium.cz/api/v0/en/calendars/general-la/${year}/${month}/${day}`;

const SOURCES = {
  litcal: {
    name: 'LitCal — Liturgical Calendar API',
    homepage: 'https://litcal.johnromanodorazio.com',
    repository: 'https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI',
    license: 'Apache-2.0',
    apiVersion: 'dev',
    engine: 'PHP',
  },
  calapi: {
    name: 'calapi — In Adiutorium church calendar API',
    homepage: 'http://calapi.inadiutorium.cz',
    repository: 'https://github.com/igneus/church-calendar-api',
    license: 'LGPL-3.0-or-later',
    apiVersion: 'v0',
    engine: 'Ruby',
  },
};

/** Fetch JSON with a few retries (calapi is per-day and occasionally slow). */
async function getJson(url) {
  let lastErr;
  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      const res = await fetch(url);
      if (res.ok) {
        return res.json();
      }
      lastErr = new Error(`HTTP ${res.status}`);
    } catch (err) {
      lastErr = err;
    }
    await new Promise((r) => setTimeout(r, 400 * (attempt + 1)));
  }
  throw new Error(`${url}: ${lastErr}`);
}

/** Every civil-year date as [y, m, d]. */
function datesOfYear(year) {
  const out = [];
  const d = new Date(Date.UTC(year, 0, 1));
  while (d.getUTCFullYear() === year) {
    out.push([d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate()]);
    d.setUTCDate(d.getUTCDate() + 1);
  }
  return out;
}

const iso = (y, m, d) => `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

/**
 * LitCal: reduce the whole-year response to one principal record per date — the
 * highest-grade, non-vigil event of the day (the office the Church observes).
 */
async function harvestLitCal(year) {
  const body = await getJson(LITCAL(year));
  const byDate = new Map();
  for (const e of body.litcal) {
    if (e.is_vigil_mass) {
      continue;
    }
    const date = String(e.date).slice(0, 10);
    if (date.slice(0, 4) !== String(year)) {
      continue;
    }
    const cur = byDate.get(date);
    if (!cur || e.grade > cur.grade) {
      byDate.set(date, e);
    }
  }
  return [...byDate.entries()]
    .sort((a, b) => (a[0] < b[0] ? -1 : 1))
    .map(([date, e]) => ({
      date,
      grade: e.grade,
      grade_lcl: e.grade_lcl,
      colors: e.color,
      key: e.event_key,
      name: e.name,
    }));
}

/**
 * calapi: one record per date carrying the ordered `celebrations` — [0] is the
 * obligatory/ferial principal, and any `optional memorial` entries follow (kept for the
 * optional-memorials cross-check, #260/#108). Stored raw; the harness normalises.
 */
async function harvestCalApi(year) {
  const rows = [];
  for (const [y, m, d] of datesOfYear(year)) {
    const body = await getJson(CALAPI(y, m, d));
    rows.push({
      date: iso(y, m, d),
      season: body.season,
      celebrations: body.celebrations.map((c) => ({
        title: c.title,
        colour: c.colour,
        rank: c.rank,
        rank_num: c.rank_num,
      })),
    });
    if (rows.length % 61 === 0) {
      process.stdout.write(`    calapi ${iso(y, m, d)} (${rows.length})\n`);
    }
  }
  return rows;
}

async function main(firstYear, lastYear, harvestedOn) {
  mkdirSync(join(OUT_DIR, 'litcal'), { recursive: true });
  mkdirSync(join(OUT_DIR, 'calapi'), { recursive: true });

  const files = { litcal: [], calapi: [] };
  for (let year = firstYear; year <= lastYear; year++) {
    process.stdout.write(`  ${year}:\n`);
    const litcal = await harvestLitCal(year);
    writeFileSync(join(OUT_DIR, 'litcal', `${year}.ndjson`), litcal.map((r) => JSON.stringify(r)).join('\n') + '\n');
    files.litcal.push({ year, days: litcal.length });
    process.stdout.write(`    litcal ${litcal.length} days\n`);

    const calapi = await harvestCalApi(year);
    writeFileSync(join(OUT_DIR, 'calapi', `${year}.ndjson`), calapi.map((r) => JSON.stringify(r)).join('\n') + '\n');
    files.calapi.push({ year, days: calapi.length });
    process.stdout.write(`    calapi ${calapi.length} days\n`);
  }

  const provenance = {
    sources: SOURCES,
    harvestedOn,
    range: { firstYear, lastYear },
    files,
    edition: 'roman:novus-ordo-2002',
    fields: {
      litcal: {
        date: 'ISO date (YYYY-MM-DD)',
        grade: 'LitCal numeric grade: 0 weekday, 1 commemoration, 2 optional memorial, '
          + '3 memorial, 4 feast, 5 feast of the Lord, 6 solemnity, 7 higher solemnity',
        grade_lcl: "the grade's Latin label",
        colors: 'liturgical colour word(s): white, red, green, purple, rose',
        key: "LitCal's stable event key (for readable reports; not compared)",
        name: "the office's Latin name (for readable reports; not compared)",
      },
      calapi: {
        date: 'ISO date (YYYY-MM-DD)',
        season: 'calapi season: advent, christmas, lent, easter, ordinary',
        celebrations: 'ordered list; [0] is the obligatory/ferial principal, optional '
          + 'memorials follow. Each: {title, colour, rank, rank_num}. rank strings: '
          + 'Easter triduum, Primary liturgical days, solemnity, feast of the Lord, '
          + 'Sunday, feast, memorial, optional memorial, commemoration, ferial',
      },
    },
    note: 'Run-and-compare oracles only (never a data source). Two independent engines '
      + '(LitCal / PHP, calapi / Ruby) satisfy the ≥2-oracle standard. The PHP harness '
      + '(NovusOrdoOracle) normalises both — and our engine — to a coarse grade '
      + '{solemnity, feast, memorial, feria} and compares grade + colour per day; the '
      + 'residual is frozen, categorised, in known-differences.ndjson.',
  };
  writeFileSync(join(OUT_DIR, 'provenance.json'), JSON.stringify(provenance, null, 2) + '\n');
  process.stdout.write(`Wrote fixtures + provenance to ${OUT_DIR}\n`);
}

const firstYear = Number(process.argv[2] ?? 2025);
const lastYear = Number(process.argv[3] ?? 2026);
// The harvest date is passed in (not read from the clock) so a re-run with the same
// upstream data and date reproduces byte-identical provenance.
const harvestedOn = process.argv[4] ?? '2026-07-11';
main(firstYear, lastYear, harvestedOn).catch((err) => {
  process.stderr.write(String(err.stack || err) + '\n');
  process.exit(1);
});
