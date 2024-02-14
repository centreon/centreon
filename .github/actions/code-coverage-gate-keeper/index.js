const core = require('@actions/core');
const fs = require('fs');
const { exeSync } = require('child_process');

try {
  const baseCodeCoveragePercentage = core.getInput('base_code_coverage_percentage');

  exeSync('pnpx nyc report --reporter json --report-dir /tmp')

  const coverage = require('/tmp/coverage-final.json');

  console.log(baseCodeCoveragePercentage, coverage)
} catch (error) {
  core.setFailed(error.message);
}