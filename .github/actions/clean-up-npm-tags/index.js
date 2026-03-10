import { execSync } from 'child_process';
import core from '@actions/core';

const packages = ['js-config', 'ui-context', 'ui'];

const getOidcToken = async () => {
  const tokenUrl = process.env.ACTIONS_ID_TOKEN_REQUEST_URL + '&audience=npm';
  const requestToken = process.env.ACTIONS_ID_TOKEN_REQUEST_TOKEN;

  const response = await fetch(tokenUrl, {
    headers: { Authorization: `bearer ${requestToken}` }
  });
  const body = await response.json();
  core.debug(`OIDC response: ${JSON.stringify(body)}`);
  return body.value;
}

const getNpmToken = async (oidcToken) => {
  const response = await fetch('https://registry.npmjs.org/-/v1/login/oidc', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: oidcToken })
  });
  const body = await response.json();
  core.debug(`NPMToken response: ${JSON.stringify(body)}`);
  return body.token;
};

const getPackageInformation = async (dependency) => {
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
  const oidcToken = await getOidcToken();
  core.setSecret(oidcToken);
  const npmToken = await (getNpmToken(oidcToken));
  core.setSecret(npmToken);
  execSync(`npm config set "//registry.npmjs.org/:_authToken" "${npmToken}"`);

  await Promise.all(
    packages.map(async (dependency) => {
      const packageInformations = await getPackageInformation(dependency);
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
