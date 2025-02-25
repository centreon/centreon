import fs from 'fs';

const reportFile = process.argv[2];
const repo = process.argv[3];
const branch = process.argv[4];
const urlFilePrefix = process.argv[5];

if (!reportFile) {
  throw new Error('No report file provided');
}

if (!fs.existsSync(reportFile)) {
  throw new Error(`Report file ${reportFile} does not exist`);
}

const report = JSON.parse(fs.readFileSync(reportFile, 'utf8'));

const totalSuites = report.stats.suites;
const totalTests = report.stats.tests;
const totalPasses = report.stats.passes;
const totalPending = report.stats.pending;
const totalFailures = report.stats.failures;
const duration = report.stats.duration / 1000;
const passPercent = report.stats.passPercent;

const summary = `<h1>Cypress Test summary</h1>
<ul>
  <li>:file_folder: Suites: ${totalSuites}</li>
  <li>:page_facing_up: Tests: ${totalTests}</li>
  <li>:white_check_mark: Passes: ${totalPasses}</li>
  <li>:hourglass: Pending: ${totalPending}</li>
  <li>:x: Failures: ${totalFailures}</li>
  <li>:bar_chart: Pass percent: ${passPercent}%</li>
  <li>:stopwatch: Duration: ${duration} seconds</li>
</ul>`;

const getTestsBySuite = (suite) => {
  if (suite.suites.length === 0) {
    return suite.tests;
  }

  return [...suite.tests.filter(({ fail }) => fail), ...suite.suites.map((subSuite) => getTestsBySuite(subSuite))];
}

const testsDetails = report.results.map((result) => ({
  file: result.file,
  tests: getTestsBySuite(result).flat(Infinity),
}));

const details =
  testsDetails.map(({ file, tests }) => {
    return `
      <details>
        <summary><h2>${file}</h2></summary>
        ${tests.map(({ fullTitle, err, fail, duration }) => {
          if (! fail) {
            return;
          }

          const sanitizedEStack = err.estack ? `<pre>${err.estack}</pre>` : '';
          return `<h3>:x: ${fullTitle}</h3>
            Duration: ${duration / 1000} seconds
            Error: ${err.message || ''}
            Error stack: ${sanitizedEStack}
            <br />`;
        }).join('')}
      </details>`;
  });

const newReportContent = `${summary}${details}`;

console.log(newReportContent);

fs.writeFileSync('cypress-report.md', newReportContent);
