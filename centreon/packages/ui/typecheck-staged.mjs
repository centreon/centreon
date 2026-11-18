// Copyright 2025-Present Centreon
// SPDX-License-Identifier: Apache-2.0

import { execSync, spawnSync } from 'node:child_process';
import { readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { exit } from 'node:process';
import { fileURLToPath } from 'node:url';

const packageDir = dirname(fileURLToPath(import.meta.url));
const tsconfig = JSON.parse(
  readFileSync(resolve(packageDir, 'tsconfig.typecheck.json'), 'utf-8')
);
const repoRoot = execSync('git rev-parse --show-toplevel').toString().trim();
const packageRelToRepo = relative(repoRoot, packageDir).replace(/\\/g, '/');
const stagedPathPrefix = `${packageRelToRepo}/src`;

const diffFiles = execSync('git diff --cached --name-only --diff-filter=ACMR')
  .toString()
  .split('\n')
  .filter(
    (file) =>
      file.startsWith(stagedPathPrefix) &&
      (file.endsWith('.ts') || file.endsWith('.tsx'))
  );

if (diffFiles.length === 0) {
  console.log(`No ${packageRelToRepo} files to type-check. Skipping...`);
  exit(0);
}

console.log(
  `Type-checking ${diffFiles.length} staged file(s) in ${packageRelToRepo}...`
);

const tmpTsconfigPath = resolve(packageDir, 'tsconfig.tmp.json');

const createTempTsconfig = (files) => {
  const newTsconfig = {
    ...tsconfig,
    include: files
  };

  writeFileSync(tmpTsconfigPath, JSON.stringify(newTsconfig, null, 2));
};

const removeTempTsconfig = () => {
  unlinkSync(tmpTsconfigPath);
};

const stagedIncludes = diffFiles.map(
  (file) =>
    `./${relative(packageDir, resolve(repoRoot, file)).replace(/\\/g, '/')}`
);

// Always include ambient type declarations so globals (JSX, vite-env,
// data-attributes, etc.) remain visible to the staged files.
const ambientIncludes = ['./src/**/*.d.ts'];

createTempTsconfig([...ambientIncludes, ...stagedIncludes]);

const { stdout, stderr, status } = spawnSync(
  'npx',
  ['tsc', '-p', tmpTsconfigPath, '--noEmit'],
  { encoding: 'utf-8', cwd: packageDir }
);

if (stdout) {
  console.log(stdout);
}
if (stderr) {
  console.error(stderr);
}

removeTempTsconfig();

if (status === 0) {
  console.log('Type-check passed!');
} else {
  console.error(
    'Type-check failed. Please fix the errors above before committing.'
  );
}

exit(status ?? 1);
