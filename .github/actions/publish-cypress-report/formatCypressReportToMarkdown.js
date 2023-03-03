import fs from  'fs';

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
const totalSkipped = report.stats.skipped;
const duration = report.stats.duration / 1000;

const summary = `<h1>Cypress Test summary</h1>
<ul>
  <li>:file_folder: Suites: ${totalSuites}</li>
  <li>:page_facing_up: Tests: ${totalTests}</li>
  <li>:white_check_mark: Passes: ${totalPasses}</li>
  <li>:hourglass: Pending: ${totalPending}</li>
  <li>:x: Failures: ${totalFailures}</li>
  <li>:fast_forward: Skipped: ${totalSkipped}</li>
  <li>:stopwatch: Duration: ${duration} seconds</li>
</ul>`;

const getTestsBySuite = (suite) => {
  if (suite.suites.length === 0) {
    return suite.tests;
  }

  return [...suite.tests.filter(({ fail, skipped }) => fail || skipped), ...suite.suites.map((subSuite) => getTestsBySuite(subSuite))];
}


export const mapSeries = async ({
  array,
  callback,
}) => {
  const result = [];
  for (const element of array) {
    result.push(await callback.call(this, await element));
  }
  return result;
};

const testsDetails = report.results.map((result) => ({
  file: result.file,
  tests: getTestsBySuite(result).flat(Infinity),
}));

const details = 
  testsDetails.map(({ file, tests }) => `<details>
    <summary>${file}</summary>
    <table>
      <thead>
        <tr>
          <th>Test</th>
          <th>Error stack</th>
          <th>State</th>
          <th>Duration (seconds)</th>
        </tr>
      </thead>
      <tbody>
        ${tests.map(({ fullTitle, err, fail, duration }) => {
          const errorLine = err.estack ? err.estack.split('\n')[1] : '';
          const isLoggableFile = errorLine.includes(file);
          if (!isLoggableFile) {
            const sanitizedEStack = err.estack ? `<pre>${err.estack.replaceAll(/</g, '&lt;').replaceAll(/>/g, '&gt;')}</pre>` : '';
            return `<tr>
              <td>${fullTitle}</td>
              <td>${fail ? ':x:' : ':fast_forward:'}</td>
              <td>${sanitizedEStack}</td>
              <td>${duration / 1000}</td>
            </tr>`;
          }
          const [,,lineNumber] = errorLine.split(':');
          const sanitizedErrorMessage = err.message ? err.message.replaceAll(/</g, '&lt;').replaceAll(/>/g, '&gt;') : '';
          const error = `Located at: <a target="_blank" href="https://github.com/${repo}/tree/${branch}/${urlFilePrefix}/${file}#L${lineNumber}">${file}:${lineNumber}</a>\n<pre>${sanitizedErrorMessage}</pre>`;
          return `<tr>
              <td>${fullTitle}</td>
              <td>${fail ? ':x:' : ':fast_forward:'}</td>
              <td>${error}</td>
              <td>${duration / 1000}</td>
            </tr>`;
        })}
      </tbody>
    </table>
  </details>`).join('');

const newReportContent = `${summary}${details}`;

fs.writeFileSync('cypress-report.md', newReportContent);