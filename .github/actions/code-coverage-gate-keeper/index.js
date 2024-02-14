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
			page: page,
		});
		results = results.concat(response.data);
		page = page + 1;
	} while (response.data.length === limit)

	return results.filter(
		comment => !!comment.user && comment.body.includes(title),
	)
}

const deleteOldComments = async ({ octokit, context, title }) => {
  const existingComments = await getExistingComments({ octokit, context, title })

  existingComments.forEach((existingComment) => {
    core.debug(`Deleting comment: ${existingComment.id}`)
		try {
			octokit.rest.issues.deleteComment({
				owner: context.repo.owner,
				repo: context.repo.repo,
				comment_id: existingComment.id,
			})
		} catch (error) {
			console.error(error)
		}
  })
}

const run = async () => {
  try {
    const modulePath = core.getInput('module_path');
    const githubToken = core.getInput('github_token');
    const name = core.getInput('name');

    if (context.payload.pull_request === null) {
      return;
    }

    execSync('pnpx nyc report --reporter json-summary --report-dir /tmp');

    const coverageFile = fs.readFileSync('/tmp/coverage-summary.json');
    const coverage = JSON.parse(coverageFile);

    const package = fs.readFileSync(`${modulePath}/package.json`);
    const baseCodeCoveragePercentage =JSON.parse(package).baseCodeCoveragePercentage

    const codeCoverageLines = coverage.total.lines.pct;

    const passGateKeep = codeCoverageLines >= baseCodeCoveragePercentage;

    const octokit = getOctokit(githubToken);

    const title = `Code Coverage Check on ${name}`;
    
    await deleteOldComments({ octokit, context, title })

    core.info(`Pass the gate keep? ${passGateKeep} (INFO: lines: ${codeCoverageLines}, base percentage: ${baseCodeCoveragePercentage})`)
    
    if (!passGateKeep) {
      const pull_request_number = context.payload.pull_request.number;
      octokit.rest.issues.createComment({
        ...context.repo,
        issue_number: pull_request_number,
        body: `<h2>📋 ${title} ❌</h2>
        Your code coverage is <b>${codeCoverageLines}%</b> but the required code coverage is <b>${baseCodeCoveragePercentage}%</b>.`
      });
      core.setFailed(`Does not pass the code coverage check (${codeCoverageLines}% instead of ${baseCodeCoveragePercentage}%)`);
    }
  } catch (error) {
    core.setFailed(error.message);
  }
}

run();