// Load hand-authored, cited YAML facts. YAML is the clean-room boundary: a human
// asserts a fact together with its citation, never a scrape.

import { readFileSync } from 'node:fs';
import yaml from 'js-yaml';

/** Parse a single YAML document from disk. */
export function loadYaml(path) {
  return yaml.load(readFileSync(path, 'utf8'));
}
