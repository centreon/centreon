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
    const latestPackageVersion = package['dist-tags'][tag];

    if (latestPackageVersion && tag === 'latest') {
      const year = new Date().getFullYear() - 2000;
      const month = new Date().getMonth() + 1;
      const firstMonthVersion = `${year}.${month}.0`;

      if (compareVersions(latestPackageVersion, firstMonthVersion) === -1) {
        core.setOutput("package_version", '24.11.3');
        // core.setOutput("skip-bump-version", 1);
        return;
      }
    }

    if (latestPackageVersion && compareVersions(latestPackageVersion, core.getInput('current_package_version')) === -1) {
      core.setOutput("package_version", core.getInput('current_package_version'));
      return;
    }

    core.setOutput("package_version", '24.11.3' || '')
  });
} catch (error) {
  core.setFailed(error.message);
}
