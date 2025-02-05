import { execSync } from 'child_process';
import core from '@actions/core';

const packages = ['js-config', 'ui-context', 'ui'];

const getPackageInformations = async (dependency) => {
  const response = await fetch(
    `https://registry.npmjs.org/@centreon/${dependency}`
  );
  return await response.json();
};

const checkAndCleanUpTag = async ({ dependency, branch }) => {
  core.info(`${dependency}: Retrieving branch for ${branch}...`);
  const d = await fetch(`https://github.com/centreon/centreon/tree/${branch}`);

  if (d.status !== 404 || branch === 'latest') {
    core.info(`${dependency}: ${branch} branch found on Github. Skipping it.`);
    return;
  }

  core.info(
    `${dependency}: ${branch} branch not found on Github. Cleaning up the NPM tag...`
  );

  execSync(`npm dist-tag rm @centreon/${dependency} ${branch}`);
  core.info(`${dependency}: ${branch} tag removed.`);
  return;
};

const run = async () => {
  core.info('Logging in to NPM registry...');
  execSync(
    `npm config set "//registry.npmjs.org/:_authToken" "${core.getInput('npm_token')}"`
  );
  core.info('Logged in');

  await Promise.all(
    packages.map(async (dependency) => {
      const packageInformations = await getPackageInformations(dependency);
      core.debug(`Processing tags for ${dependency}...`);

      const distTags = packageInformations['dist-tags'];

      const branchNamesFromTags = Object.keys(distTags);

      let chainedPromise = Promise.resolve();
      branchNamesFromTags.forEach((branch) => {
        chainedPromise = chainedPromise.then(() => {
          return checkAndCleanUpTag({ dependency, branch });
        });
      });
      core.debug(`${dependency} tags processed...`);
    })
  );
};

run().finally(() => {
  execSync('npm config delete "//registry.npmjs.org/:_authToken"');
});
