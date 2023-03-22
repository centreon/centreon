const fs = require('fs');

const reportFile = process.argv[2];
const workdir = process.argv[3];

if (!fs.existsSync(reportFile)) {
  throw Error(`The report ${reportFile} does not exist`);
}

const reportFileString = fs.readFileSync(reportFile, 'utf-8');
const reportFileContent = JSON.parse(reportFileString);

const filesWithoutIssuesCount = reportFileContent.filter(
  ({ errorCount, warningCount }) => errorCount + warningCount === 0
).length;
const totalWarning = reportFileContent.reduce(
  (acc, { warningCount }) => acc + warningCount,
  0
);
const totalFixableWarning = reportFileContent.reduce(
  (acc, { fixableWarningCount }) => acc + fixableWarningCount,
  0
);
const totalErrors = reportFileContent.reduce(
  (acc, { errorCount }) => acc + errorCount,
  0
);
const totalFixableErrors = reportFileContent.reduce(
  (acc, { fixableWarningCount }) => acc + fixableWarningCount,
  0
);

const formattedFilesLinted = `:page_facing_up: ${reportFileContent.length} files linted`;
const formattedFilesHadNoIssues = `:white_check_mark: ${filesWithoutIssuesCount} files had no issues`;
const formattedTotalWarning = `:warning: ${totalWarning} total warning (${totalFixableWarning} fixable)`;
const formattedTotalError = `:x: ${totalErrors} total errors (${totalFixableErrors} fixable)`;

const summary = `<h1>Summary</h1>
<ul>
  <li>${formattedFilesLinted}</li>
  <li>${formattedFilesHadNoIssues}</li>
  <li>${formattedTotalWarning}</li>
  <li>${formattedTotalError}</li>
</ul>`;

const filesWithIssue = reportFileContent.filter(
  ({ errorCount, warningCount }) => errorCount + warningCount > 0
);

const details = `${filesWithIssue.map(
  ({ messages, errorCount, warningCount, filePath }) => `<details>
        <summary>${filePath.replace(
          workdir,
          ''
        )} (:warning:: ${warningCount}, :x:: ${errorCount})</summary>
        <table>
          <thead>
            <tr>
              <th>Message</th>
              <th>Rule</th>
              <th>Line</th>
              <th>Column</th>
              <th>severity</th>
            </tr>
          </thead>
          <tbody>
          ${messages.map(
            ({ severity, ruleId, message, line, column }) => `<tr>
              <td>${message}</td>
              <td>${ruleId}</td>
              <td>${line}</td>
              <td>${column}</td>
              <td>${severity === 1 ? ':warning:' : ':x:'}</td>
            </tr>`
          )}
          </tbody>
        </table>
      </details>
    `
)}
`;

const report = `
${summary}
${details}
`;

fs.writeFileSync('eslint-report.md', report, 'utf-8');