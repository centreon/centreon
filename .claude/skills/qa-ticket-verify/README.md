# qa-ticket-verify

Automates the manual-QA pass on a Jira ticket (project `MON`): reads the ticket, finds its
linked PR, spins up that PR's build locally with Docker, drives the real frontend through
Playwright against a Gherkin scenario derived from the ticket + PR description + diff, and
reports a verdict. **It never transitions the Jira ticket itself** — a human still makes that
call from the report.

Full agent instructions live in [SKILL.md](SKILL.md). This file is the human-facing quickstart.

## Using it interactively

In a Claude Code session inside this repo, with the Playwright MCP connected
(`claude mcp add playwright -- npx -y @playwright/mcp@latest`), just ask:

> QA le ticket MON-12345

The skill reads the ticket, finds the PR via `gh pr list`, brings up the local Docker
environment, exercises the scenario, and reports back in the conversation.

## Using it in CI (`.github/workflows/qa-ticket-verify.yml`)

### One-time setup, per contributor

`CLAUDE_CODE_OAUTH_TOKEN` is a personal secret, not a shared one — each contributor sets
their own, self-serve, no admin/repo-owner involvement needed:

```bash
claude setup-token
gh secret set CLAUDE_CODE_OAUTH_TOKEN_<your-github-username> --repo centreon/centreon
```

The workflow resolves the right token dynamically from whoever triggered the run
(`secrets[format('CLAUDE_CODE_OAUTH_TOKEN_{0}', github.actor)]`), so this is the only setup
step — nothing to add to the workflow file itself. A `check-token` job fails fast (in
seconds) with a clear message if you haven't set yours yet.

### Triggering a run

```bash
gh workflow run qa-ticket-verify.yml --repo centreon/centreon -f jira_key=MON-12345
```

Or from the Actions tab, once this workflow is on `develop`: **Run workflow** → enter the
Jira key.

### What it does

1. Finds the PR linked to the ticket, resolves its OS variant (alma9/alma10...) from the
   PR's own CI checks, and brings up `.github/docker/docker-compose.yml` with that build.
2. Verifies the environment is actually reachable and DB-backed before continuing.
3. Runs the skill headlessly (`claude -p`, capped at `--max-budget-usd 3`) to write and
   execute the Gherkin scenario via Playwright, recording a video of the session.
4. Posts the verdict as a Jira comment on the ticket and stops — no status transition.

### Outputs

Uploaded as a workflow artifact (`qa-ticket-verify-<jira-key>`, 14-day retention):
the generated `.feature` file, the raw and human-readable Claude transcripts
(`claude-output.jsonl` / `claude-progress.log`), the updated selector catalog, and the
Playwright session recording/screenshots.

## Known limitations

- Assumes the linked PR touches `centreon-web` (hardcoded `centreon-web-slim-<os>` image).
  Tickets whose PR touches a different monorepo module will fail at environment startup.
- Jira comments post as whatever account owns the shared `XRAY_JIRA_USER_EMAIL` secret —
  only the Claude token is per-person, not the Jira identity.
