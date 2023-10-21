const core = require('@actions/core');
const { Octokit } = require('octokit');
const { execSync } = require('child_process');
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
  const response = await octokitClient.request('GET /repos/centreon/centreon/actions/artifacts?name=lighthouse-report&per_page=100', {
    owner: 'centreon',
    repo: 'centreon',
  });

  const lighthouseReport = response.data.artifacts.find(({ workflow_run }) => workflow_run.head_branch === baseBranch);

  const download = await fetch(lighthouseReport.archive_download_url);
  const fileStream = fs.createWriteStream('report');
  await new Promise((resolve, reject) => {
      res.body.pipe(fileStream);
      res.body.on("error", reject);
      fileStream.on("finish", resolve);
    });

  execSync('ls', {
    stdio: 'inherit'
  })

  execSync('ls report', {
    stdio: 'inherit'
  })
}

getBaseArtifact();