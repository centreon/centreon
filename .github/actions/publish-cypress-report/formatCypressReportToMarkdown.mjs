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

  return [...suite.tests.filter(({ fail, skipped }) => fail || skipped), ...suite.suites.map((subSuite) => getTestsBySuite(subSuite))];
}

const mapSeries = async ({ array, callback }) => {
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
  await mapSeries({ array: testsDetails, callback: async ({ file, tests }) => `
<div>
  <h2>${file} :arrow_down_small:</h2>
  ${await mapSeries({ array: tests, callback: async ({ fullTitle, err, fail, duration }) => {
    const stackLines = err.estack ? err.estack.split('\n') : [];
      const localizableFile = stackLines.find((line) => line.includes(file));

      if (!localizableFile) {
        const sanitizedEStack = err.estack ? `<pre>${err.estack}</pre>` : '';
        return `<h3>${fail ? ':x:' : ':leftwards_arrow_with_hook:'} ${fullTitle}</h3>
        Duration: ${duration / 1000} seconds
        ${fail ? `Error stack: ${sanitizedEStack}` : '<br />'}
        <br />`;
      }
      const [,,lineNumber] = localizableFile.split(':');
      const errorMessage = err.message || '';
      const response = await fetch(`https://raw.githubusercontent.com/${repo}/${branch}/${urlFilePrefix}/${file}`);
      const upstreamFile = await response.text();
      const locatedLine = upstreamFile.split('\n')[lineNumber - 1];
      const error = `Located at: <a target="_blank" href="https://github.com/${repo}/tree/${branch}/${urlFilePrefix}/${file}#L${lineNumber}">${file}:${lineNumber}</a>`;
      return `<h3>${fail ? ':x:' : ':leftwards_arrow_with_hook:'} ${fullTitle}</h3>
        Duration: ${duration / 1000} seconds
        <div>${error}</div>
        <div>The following line fails the test: <code>${locatedLine}</code></div>
        <details>
          <summary>Complete logs</summary>
          <pre>${errorMessage.split('\n')[0]}</pre>
        </details>
        <br />`;
  }}).then((v) => v.join(''))}
</div>` }).then((v) => v);

const newReportContent = `${summary}${details}`;

fs.writeFileSync('cypress-report.md', newReportContent);