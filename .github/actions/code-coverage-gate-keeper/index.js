const core = require('@actions/core');
const { getOctokit, context } = require('@actions/github');
const fs = require('fs');
const { execSync } = require('child_process');

const limit = 20;

const getExistingComments = async ({ octokit, context, title }) => {
  let page = 0;
  let results = [];
  let response;

  do {
    response = await octokit.rest.issues.listComments({
      issue_number: context.issue.number,
      owner: context.repo.owner,
      repo: context.repo.repo,
      per_page: limit,
      page: page
    });
    results = results.concat(response.data);
    page = page + 1;
  } while (response.data.length === limit);

  return results.filter(
    (comment) => !!comment.user && comment.body.includes(title)
  );
};

const deleteOldComments = async ({ octokit, context, title }) => {
  const existingComments = await getExistingComments({
    octokit,
    context,
    title
  });

  existingComments.forEach((existingComment) => {
    core.debug(`Deleting comment: ${existingComment.id}`);
    try {
      octokit.rest.issues.deleteComment({
        owner: context.repo.owner,
        repo: context.repo.repo,
        comment_id: existingComment.id
      });
    } catch (error) {
      console.error(error);
    }
  });
};

const run = async () => {
  try {
    const modulePath = core.getInput('module_path');
    const githubToken = core.getInput('github_token');
    const name = core.getInput('name');
    const dynamicCodeCoveragesFilePath = core.getInput(
      'dynamicCodeCoveragesFilePath'
    );
    const generateNewCodeCoverages = core.getBooleanInput(
      'generateNewCodeCoverages'
    );

    if (context.payload.pull_request === null) {
      return;
    }

    execSync('pnpx nyc report --reporter json-summary --report-dir /tmp');

    const coverageFile = fs.readFileSync('/tmp/coverage-summary.json');
    const coverage = JSON.parse(coverageFile);
    const module = modulePath.replaceAll('/', '-');
    const codeCoverageLines = coverage.total.lines.pct;
    const codeCoverages = JSON.parse(
      fs.readFileSync(dynamicCodeCoveragesFilePath)
    );
    const baseCodeCoveragePercentage = codeCoverages[module];
    const lowerBaseCodeCoverage = baseCodeCoveragePercentage - 0.04;

    const passGateKeep =
      codeCoverageLines >= lowerBaseCodeCoverage ||
      codeCoverageLines >= baseCodeCoveragePercentage;
    const strictlyPassGateKeep =
      codeCoverageLines >= baseCodeCoveragePercentage;

    if (generateNewCodeCoverages) {
      if (!strictlyPassGateKeep) {
        core.info(
          `Cannot update base percentage for ${module}. Requirement: ${baseCodeCoveragePercentage}%. Current: ${codeCoverageLines}%`
        );
        return;
      }
      const newCodeCoverages = {
        ...codeCoverages,
        [module]: codeCoverageLines
      };
      fs.writeFileSync(
        '/tmp/newBaseCodeCoverages.json',
        JSON.stringify(newCodeCoverages)
      );
      return;
    }

    const octokit = getOctokit(githubToken);

    const title = `Code Coverage Check on ${name}`;

    await deleteOldComments({ octokit, context, title });

    core.info(
      `Does it pass the gate keep? ${passGateKeep} (INFO: lines: ${codeCoverageLines}, base percentage: ${baseCodeCoveragePercentage})`
    );

    if (!passGateKeep) {
      const pullRequestNumber = context.payload.pull_request.number;
      octokit.rest.issues.createComment({
        ...context.repo,
        issue_number: pullRequestNumber,
        body: `<h2>📋 ${title} ❌</h2>
        Your code coverage is <b>${codeCoverageLines}%</b> but the required code coverage is <b>${baseCodeCoveragePercentage}%</b>.`
      });
      core.setFailed(
        `Does not pass the code coverage check (${codeCoverageLines}% instead of ${baseCodeCoveragePercentage}%)`
      );
    }
  } catch (error) {
    core.setFailed(error.message);
  }
};

run();
