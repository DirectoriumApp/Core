// Validate generated records against the frozen corpus JSON Schemas (issue #39,
// draft 2020-12). The schemas live in the engine's data/corpus/schema and are the
// single contract shared with the PHP loader; validating here fails the build
// before an invalid record can ever be committed.

import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import Ajv2020 from 'ajv/dist/2020.js';

/**
 * Compile every `*.schema.json` in `schemaDir` and return a map of
 * shape-name (the filename without `.schema.json`) to its Ajv validator.
 * The schemas are self-contained (inline $defs, no cross-file $ref), so each
 * compiles independently; readdir order is irrelevant because the result is a
 * name-keyed map, not ordered output.
 */
export function makeValidators(schemaDir) {
  // strict mode catches unknown/misspelled keywords (a silent enforcement gap),
  // but strictRequired/strictTypes over-flag valid draft-2020 patterns the frozen
  // #39 schemas use (required inside $ref'd $defs and if/allOf branches), so those
  // two sub-checks are relaxed while keyword typos still fail compilation.
  const ajv = new Ajv2020({ allErrors: true, strict: true, strictRequired: false, strictTypes: false });
  const validators = {};
  for (const file of readdirSync(schemaDir).sort()) {
    if (!file.endsWith('.schema.json')) {
      continue;
    }
    const schema = JSON.parse(readFileSync(join(schemaDir, file), 'utf8'));
    const shape = file.replace(/\.schema\.json$/, '');
    validators[shape] = ajv.compile(schema);
  }
  return validators;
}

/**
 * Validate every record in `records` against `validator`. Returns a list of
 * human-readable error strings (empty when all records conform).
 */
export function validateAll(validator, records, shape) {
  const errors = [];
  records.forEach((record, index) => {
    if (!validator(record)) {
      const id = record && record.id ? record.id : '?';
      errors.push(`${shape}[${index}] (${id}): ${JSON.stringify(validator.errors)}`);
    }
  });
  return errors;
}
