const core = require('@actions/core');
const { Octokit } = require('octokit');

const pat = core.getInput('pat');
const baseBranch = core.getInput('base_branch');

const octokitClient = new Octokit({
  auth: pat
});

const getBaseArtifact = async () => {
  const artifacts = await octokitClient.request('GET /repos/centreon/centreon/actions/artifacts?name=lighthouse-report', {
    owner: 'centreon',
    repo: 'centreon',
  });

  console.log(artifacts)

  const lighthouseBaseBranch = artifacts.artifacts.find(({ workflow_run }) => workflow_run.head_branch === baseBranch);

  console.log(lighthouseBaseBranch)
}

getBaseArtifact();