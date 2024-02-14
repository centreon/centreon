const core = require('@actions/core');
const fs = require('fs');
const { execSync } = require('child_process');

try {
  const modulePath = core.getInput('module_path');

  execSync('pnpx nyc report --reporter json-summary --report-dir /tmp')

  const coverageFile = fs.readFileSync('/tmp/coverage-summary.json');
  const coverage = JSON.parse(coverageFile);

  const package = fs.readFileSync(`${modulePath}/package.json`);
  const baseCodeCoveragePercentage =JSON.parse(package).baseCodeCoveragePercentage

  const codeCoverageStatements = coverage.total.statements.pct;

  const doesNotPassGateKeep = codeCoverageStatements < baseCodeCoveragePercentage;

  console.log(baseCodeCoveragePercentage, coverage.total.statements.pct, doesNotPassGateKeep)


} catch (error) {
  core.setFailed(error.message);
}