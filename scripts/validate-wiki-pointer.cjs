const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const repo = path.basename(root);
const pointer = fs.readFileSync(path.join(root, 'docs/wiki.md'), 'utf8');
const expected = `https://github.com/ControleOnline/${repo}/wiki\n`;
if (pointer !== expected) throw new Error(`docs/wiki.md must contain only ${expected.trim()}`);
const gitmodulesPath = path.join(root, '.gitmodules');
if (fs.existsSync(gitmodulesPath)) {
  const gitmodules = fs.readFileSync(gitmodulesPath, 'utf8');
  if (/path\s*=\s*docs\/wiki/.test(gitmodules) || new RegExp(`${repo}\\.wiki\\.git`).test(gitmodules)) throw new Error('docs/wiki must not be configured as a wiki submodule');
}
console.log(`Validated wiki pointer for ${repo}.`);
