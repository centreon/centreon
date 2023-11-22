const core = require('@actions/core');
const { compareVersions } = require('compare-versions');

const getPackageInformations = async () => {
  const package = core.getInput('package');

  const response = await fetch(`https://registry.npmjs.org/@centreon/${package}`);
  return await response.json();
}

try {
  const gitBranchName = core.getInput('branch_name');

  const tag = gitBranchName === 'develop' ? 'latest' : gitBranchName;

  getPackageInformations().then((package) => {
    console.log(compareVersions(package['dist-tags']["latest"], core.getInput('packageVersion')))
    core.setOutput("package_version", package['dist-tags'][tag] || '')
  });
} catch (error) {
  core.setFailed(error.message);
}