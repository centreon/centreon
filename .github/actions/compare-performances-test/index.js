const core = require('@actions/core');
const { Octokit } = require('octokit');
const fetch = require('node-fetch');

const pat = core.getInput('pat');
const baseBranch = core.getInput('base_branch');

const octokitClient = new Octokit({
  auth: pat,
  request: {
    fetch
  }
});

const getBaseArtifact = async () => {
  const response = await octokitClient.request('GET /repos/centreon/centreon/actions/artifacts?name=lighthouse-report', {
    owner: 'centreon',
    repo: 'centreon',
  });

  console.log(response.data.artifacts)

  const lighthouseBaseBranch = response.data.artifacts.find(({ workflow_run }) => workflow_run.head_branch === baseBranch);

  console.log(lighthouseBaseBranch)
}

getBaseArtifact();