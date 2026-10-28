import core from '@actions/core';

const registry = 'https://registry.npmjs.org';
const packages = ['js-config', 'ui-context', 'ui'];
const keptTags = new Set(['latest']);
// Prerelease versions are published as <x.y.z>-<branch>.<n>
const prereleaseVersion = /^\d+\.\d+\.\d+-(.+)\.\d+$/;

const npmToken = core.getInput('npm_token');
const githubToken = core.getInput('github_token');
const dryRun = core.getBooleanInput('dry_run');

// Older publications replaced dots in branch names, compare on a common form
const normalize = (name) => name.replace(/[^0-9A-Za-z-]/g, '-');

const npmFetch = (path, options = {}) =>
  fetch(`${registry}${path}`, {
    ...options,
    headers: {
      Authorization: `Bearer ${npmToken}`,
      'Content-Type': 'application/json',
      ...options.headers
    }
  });

const checkNpmAuthentication = async () => {
  if (!npmToken) {
    throw new Error('NPM_TOKEN is empty.');
  }

  const response = await npmFetch('/-/whoami');
  if (!response.ok) {
    throw new Error(
      `NPM_TOKEN is rejected by the registry (HTTP ${response.status}), it must be renewed.`
    );
  }

  const { username } = await response.json();
  core.info(`Authenticated on NPM registry as ${username}`);
};

const listBranches = async () => {
  const branches = new Set();

  for (let page = 1; ; page++) {
    const response = await fetch(
      `https://api.github.com/repos/${process.env.GITHUB_REPOSITORY}/branches?per_page=100&page=${page}`,
      {
        headers: {
          Accept: 'application/vnd.github+json',
          Authorization: `Bearer ${githubToken}`
        }
      }
    );
    if (!response.ok) {
      throw new Error(`Cannot list branches (HTTP ${response.status}).`);
    }

    const batch = await response.json();
    batch.forEach(({ name }) => branches.add(normalize(name)));
    if (batch.length < 100) {
      break;
    }
  }

  // An incomplete list would flag live branches as deleted
  if (!branches.has('develop')) {
    throw new Error('develop is missing from the branch list, aborting.');
  }

  core.info(`${branches.size} branches found on GitHub`);
  return branches;
};

const cleanUpPackage = async ({ name, branches }) => {
  const escapedName = `@centreon%2f${name}`;
  const errors = [];

  // Full document with its revision, as npm deprecate does
  const response = await npmFetch(`/${escapedName}?write=true`);
  if (!response.ok) {
    throw new Error(`@centreon/${name}: cannot fetch package (HTTP ${response.status}).`);
  }
  const packument = await response.json();

  const deadTags = [];
  const protectedVersions = new Set();
  Object.entries(packument['dist-tags']).forEach(([tag, version]) => {
    if (keptTags.has(tag) || branches.has(normalize(tag))) {
      protectedVersions.add(version);
    } else {
      deadTags.push(tag);
    }
  });

  const deprecatedVersions = [];
  Object.entries(packument.versions).forEach(([version, manifest]) => {
    const branch = version.match(prereleaseVersion)?.[1];
    if (
      !branch ||
      branches.has(normalize(branch)) ||
      protectedVersions.has(version) ||
      manifest.deprecated
    ) {
      return;
    }

    manifest.deprecated = `Prerelease of the deleted branch ${branch}, not maintained.`;
    deprecatedVersions.push(version);
  });

  core.startGroup(
    `@centreon/${name}: ${deadTags.length} tags to remove, ${deprecatedVersions.length} versions to deprecate`
  );
  deadTags.forEach((tag) => core.info(`tag ${tag}`));
  deprecatedVersions.forEach((version) => core.info(`version ${version}`));
  core.endGroup();

  if (dryRun) {
    return { name, deadTags, deprecatedVersions, errors };
  }

  // One write for all versions: the document is too large for one npm deprecate per version
  if (deprecatedVersions.length > 0) {
    const putResponse = await npmFetch(`/${escapedName}`, {
      method: 'PUT',
      body: JSON.stringify(packument)
    });
    if (!putResponse.ok) {
      errors.push(`deprecation failed (HTTP ${putResponse.status})`);
    }
  }

  // Tags are removed after the write, which carries the current dist-tags
  for (const tag of deadTags) {
    const deleteResponse = await npmFetch(
      `/-/package/${escapedName}/dist-tags/${encodeURIComponent(tag)}`,
      { method: 'DELETE' }
    );
    if (!deleteResponse.ok) {
      errors.push(`tag ${tag} removal failed (HTTP ${deleteResponse.status})`);
    }
  }

  return { name, deadTags, deprecatedVersions, errors };
};

const run = async () => {
  if (dryRun) {
    core.info('Dry run: the registry is not modified.');
  }

  await checkNpmAuthentication();
  const branches = await listBranches();

  const results = [];
  for (const name of packages) {
    results.push(await cleanUpPackage({ name, branches }));
  }

  await core.summary
    .addHeading(`NPM clean-up${dryRun ? ' (dry run)' : ''}`)
    .addTable([
      [
        { data: 'Package', header: true },
        { data: 'Removed tags', header: true },
        { data: 'Deprecated versions', header: true },
        { data: 'Errors', header: true }
      ],
      ...results.map(({ name, deadTags, deprecatedVersions, errors }) => [
        `@centreon/${name}`,
        `${deadTags.length}`,
        `${deprecatedVersions.length}`,
        `${errors.length}`
      ])
    ])
    .write();

  const errors = results.flatMap(({ name, errors: packageErrors }) =>
    packageErrors.map((error) => `@centreon/${name}: ${error}`)
  );
  if (errors.length > 0) {
    errors.forEach((error) => core.error(error));
    core.setFailed(`${errors.length} errors during the clean-up.`);
  }
};

run().catch((error) => core.setFailed(error.message));
