const core = require('@actions/core');
const fs = require('fs');
const { execSync } = require('child_process');

try {
  const baseCodeCoveragePercentage = core.getInput('base_code_coverage_percentage');

  execSync('pnpx nyc report --reporter json --report-dir /tmp')

  const coverageFile = fs.readFileSync('/tmp/coverage-final.json');
  const coverage = JSON.parse(coverageFile)

  console.log(baseCodeCoveragePercentage, coverage)
} catch (error) {
  core.setFailed(error.message);
}