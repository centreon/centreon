// Release changes summary of release-trigger-builds: full diff and per-component commit lists.
// Used through actions/github-script; inputs from the environment: BASE_SHA, HEAD_SHA,
// PREVIOUS_RELEASE_BUNDLE_TAG, NEW_BRANCH (branch or testing tag)
module.exports = async ({ core }) => {
  const fs = require('fs');

  // Data from previous steps and context
  const baseSha = process.env.BASE_SHA;
  const headSha = process.env.HEAD_SHA;
  const repo = process.env.GITHUB_REPOSITORY;
  const previousReleaseBundleTag = process.env.PREVIOUS_RELEASE_BUNDLE_TAG
  const newBranch = process.env.NEW_BRANCH

  // Artifacts from previous steps
  const componentsFile = 'artifacts/changed-components.txt';
  const commitListsDir = 'artifacts/commit-lists';

  // Build summary
  // Full diff between baseSha (latest stable bundle tag) and headSha (dev or release/hotfix branch)
  core.summary
    .addHeading('Release changes summary', 2)
    .addRaw('\n\n')
    .addRaw(`**Base (${previousReleaseBundleTag}):** \`${baseSha}\`\n\n`)
    .addRaw(`**Head (${newBranch}):** \`${headSha}\`\n\n`)
    .addLink(
      `Full diff ${previousReleaseBundleTag} <> ${newBranch}`,
      `https://github.com/${repo}/compare/${baseSha}...${headSha}`
    )
    .addRaw('\n\n');

  if (!fs.existsSync(componentsFile)) {
    core.summary.addRaw('_No components changed._\n');
    await core.summary.write();
    return;
  }

  // Get components, 1 per line from the file
  const components = fs
    .readFileSync(componentsFile, 'utf8')
    .split('\n')
    .map(l => l.trim())
    .filter(Boolean);

  let shown = false;

  for (const component of components) {
    const commitsPath = `${commitListsDir}/${component}.txt`;
    if (!fs.existsSync(commitsPath)) continue;

    const lines = fs.readFileSync(commitsPath, 'utf8')
      .split('\n')
      .map(l => l.trim())
      .filter(Boolean);

    if (lines.length === 0) continue;

    shown = true;

    const rendered = lines.map(line => {
      const [sha, ...rest] = line.split(' ');
      const subject = rest.join(' ');
      const url = `https://github.com/${repo}/commit/${sha}`;
      return `- [\`${sha}\`](${url}) ${subject}`;
    }).join('\n');

    const diffUrl = `https://github.com/${repo}/compare/${baseSha}...${headSha}`;

    core.summary.addSeparator()

    core.summary
      .addRaw(`\n\n### ${component} — [Full diff ${previousReleaseBundleTag} <> ${newBranch}](${diffUrl})\n\n`)
      .addDetails(`Commit list (${lines.length})`, `\n\n${rendered}\n`)
      .addRaw('\n');
  }

  if (!shown) {
    core.summary.addRaw('_No components changed._\n');
  }

  await core.summary.write();
};
