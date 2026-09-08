---
name: qa-ticket-verify
description: Verify a Jira ticket (project MON, Centreon) that is in "QA NEEDED" status by spinning up the PR's build locally with Docker, driving the real frontend through Playwright MCP, and checking it against a Gherkin scenario derived from the ticket description + linked PR. Use when the user asks to "QA a ticket", "vérifier un ticket", "tester le ticket MON-XXXXX", or check whether a PR is ready to move out of QA NEEDED. Also runs unattended in CI via the qa-ticket-verify workflow (workflow_dispatch).
metadata:
  version: 1.1.0
---

# QA Ticket Verify

Verifies a single Centreon Jira ticket end-to-end: reads the ticket, finds its PR, spins up a real local instance of that PR's build, exercises it with Playwright MCP against a Gherkin scenario you write from the ticket + PR content, and reports a verdict. **It never transitions the Jira ticket itself** — always end with a human-readable report and let a person move the ticket.

**Use this skill when:** the user gives you a Jira key (e.g. `MON-12345`) and asks you to QA/verify/test it, or asks whether a ticket/PR is ready to leave "QA NEEDED". Also invoked headlessly by `.github/workflows/qa-ticket-verify.yml` — see the "Running in CI" section, it changes step 0 and step 9.

**Do NOT use this skill when:** the user just wants to read a ticket (use the Atlassian MCP tools directly), or wants to run the existing Cypress suite (that's the `cypress-author`/`run` territory, not this).

Args: a single Jira issue key, e.g. `MON-207985`. If not given, ask for one before doing anything else.

## 0. Preconditions — check before doing any real work

1. **Playwright MCP.** Call `ToolSearch` with query `"browser navigate click snapshot"`. If nothing playwright-shaped comes back, STOP and tell the user: Playwright MCP isn't connected to this session; they need to add it (e.g. `claude mcp add playwright -- npx -y @playwright/mcp@latest`) or enable it via `/mcp`, then retry. Don't try to work around a missing browser tool. (In CI this is a non-issue — the workflow passes `--mcp-config` with Playwright pre-declared.)
2. **Repo.** Confirm the current working directory is the `centreon/centreon` checkout (`git remote get-url origin` should contain `centreon/centreon`). If not, ask the user to run this from that repo — all paths below are relative to its root, including this skill's own `selectors.json` and `runs/` (now project-scoped at `.claude/skills/qa-ticket-verify/`, not a personal-home path).
3. **Jira access — two modes:**
   - **Interactive session (a human is present):** cloud site is `centreon.atlassian.net`, cloudId `590f175e-b201-4613-b8a5-f1adb5eb557a`. Use the `mcp__claude_ai_Atlassian__*` tools (try the cloudId first, fall back to `getAccessibleAtlassianResources` if it errors).
   - **CI / headless (no `mcp__claude_ai_Atlassian__*` tools available):** use the Jira Cloud REST API directly via `curl`, authenticated with Basic Auth using `$JIRA_EMAIL:$JIRA_API_TOKEN` against `$JIRA_BASE_URL` (all three env vars are set by the workflow, reusing this repo's existing `XRAY_JIRA_USER_EMAIL` / `XRAY_JIRA_TOKEN` / `JIRA_BASE_URL` secrets — never print them, never put them in a prompt echoed to logs). Example read:
     ```bash
     curl -su "$JIRA_EMAIL:$JIRA_API_TOKEN" \
       "$JIRA_BASE_URL/rest/api/3/issue/$TICKET_KEY?fields=summary,description,status,comment"
     ```
     Description/comments come back in Atlassian Document Format (ADF, JSON) via this endpoint — read the `.text` nodes, or add `?expand=renderedFields` for HTML you can strip instead.
4. **Selector catalog.** Read `.claude/skills/qa-ticket-verify/selectors.json` (relative to repo root) now, once, and keep it in mind for step 5. You will append to this same file at the end.

## 1. Read the ticket and check its status

Interactive: call `mcp__claude_ai_Atlassian__getJiraIssue` (cloudId above, `issueIdOrKey` = the ticket key, `fields: ["summary","description","status","issuetype","comment"]`, `responseContentFormat: "markdown"`).

CI: the `curl` call from step 0.3.

- If `status.name` is not `QA NEEDED` (case-insensitive): interactive — tell the user the actual status and ask whether to proceed anyway before continuing. CI — proceed anyway (the workflow is only triggered intentionally, by a human picking a ticket key or a Jira automation firing specifically on the QA NEEDED transition, so a status mismatch by the time the job actually runs is a race, not a mistake — note it in the final report instead of aborting the whole run).
- Keep the rendered `description` — this is your primary source of acceptance criteria.

## 2. Find the linked PR

**CI:** already done for you by the workflow's "Find linked PR" step, before this even started — it's deterministic (no judgment call needed), so it's not worth spending a turn on. You're given `$PR_NUMBER` and `$PR_URL` directly in the prompt; skip straight to fetching its body/diff below. If the workflow found more than one candidate PR it says so in a `::warning::` — mention that in your final report so a human can double-check the pick, don't silently trust it blindly.

**Interactive:** the Jira key is almost always in the PR title or a commit message. Search GitHub, don't guess:

```bash
gh pr list --repo centreon/centreon --search "<TICKET-KEY> in:title,body" --state all \
  --json number,title,url,body,headRefName,baseRefName,state,isDraft,updatedAt
```

If that returns nothing, broaden to commit search:
```bash
gh api "search/issues?q=repo:centreon/centreon+%22<TICKET-KEY>%22+in:title,body+type:pr" --jq '.items[] | {number,title,url:.html_url,state}'
```

- Zero results: ask the user for the PR URL/number directly rather than guessing.
- Multiple results: prefer an open, non-draft PR; if still ambiguous, list the candidates and ask.

Once you have the PR number (given directly in CI, found above interactively), fetch its body and diff — this part always runs, regardless of mode:
```bash
gh pr view <NUM> --repo centreon/centreon --json title,body,files,headRefName,url
gh pr diff <NUM> --repo centreon/centreon
```

## 3. Determine the real scope (don't trust the ticket title alone)

Cross-reference three sources before writing anything:
1. **Jira description** — the stated acceptance criteria / "what should happen".
2. **PR description** — often narrows or corrects the ticket (edge cases handled, things explicitly out of scope, screenshots/GIFs of before/after). Double-check anything in a "How to test" section live rather than assuming it's accurate — PR authors do make typos in route/param names.
3. **PR diff** — the actual behavior. If the description and the diff disagree, the diff wins; note the discrepancy in your final report so the human QA-er sees it too.

Also check for **existing e2e coverage** of the touched area (`Grep`/`Glob` under `centreon/tests/e2e/features/`) so your new Gherkin adds only what's *not* already covered by the regular suite, instead of duplicating it.

## 4. Write the Gherkin scenario(s)

Write standard Gherkin covering the full scope you just extracted: the nominal case, every edge case explicitly called out in the ticket or PR description, and any regression risk the PR description flags. Save it to:

```
.claude/skills/qa-ticket-verify/runs/<TICKET-KEY>.feature
```

(create the `runs/` directory if needed — it's gitignored, this is a working artifact for traceability within a run/session, not something committed to the repo). This is not something you add to the permanent Cypress suite — mention it in the final report but don't open a PR for it unless the user asks. In CI, this file is uploaded as a workflow artifact by the workflow (not committed) so it's still retrievable after the run.

## 5. Resolve every selector before touching the browser

For each UI element your Gherkin steps will need to interact with, resolve a selector in this order, cheapest/most-reliable first:

1. **Catalog** — check `selectors.json` (read in step 0.4) for an existing entry under a matching area.
2. **The PR diff itself** — grep it for `data-testid=`, `aria-label=`, `id=` on the element being added/changed. This is the most trustworthy source since it's the exact code shipping.
3. **Existing frontend source** — if the element predates this PR, `Grep` `centreon/www/front_src/src/**` (React, `data-testid`) or the relevant `centreon/www/include/**` legacy page (plain `id`/`name` attributes) for the component.
4. **Live inspection, last resort** — once the page is loaded in step 7, use Playwright's snapshot/accessibility-tree tool to find the element by visible text or role, and derive a selector from that.

Whenever you resolve a selector via 2–4 (i.e. it wasn't already in the catalog), **append it to `selectors.json`** under a sensibly-named area (reuse an existing area key if one fits) before moving on, with a `source` note. Never blindly overwrite an existing entry — if you find conflicting info, flag it in the report instead of silently changing a selector another run may depend on. In CI, the updated `selectors.json` is uploaded as a workflow artifact; periodically diff/merge it back into the repo's copy by hand (or ask the user to) so the catalog actually accumulates across CI runs — CI does not push commits back on its own.

Note: legacy PHP pages (`main.get.php` / `main.php?p=...`) render inside an iframe `#main-content` — Playwright needs a frame-scoped locator for anything inside it, not a page-level one. In practice `browser_click`/`browser_type`'s `target` accepts a plain CSS selector directly and Playwright resolves it through the iframe automatically; `browser_evaluate` needs an explicit `document.querySelector('#main-content').contentDocument` (see `selectors.json`'s `_meta.playwrightMcpNote`).

## 6. Bring up the local environment

**CI:** already done for you by the workflow's "Resolve image tag and start local environment" step — deterministic, same reasoning as step 2. By the time you're running, `http://localhost:4000/centreon` is already up and healthy. Don't run `docker compose up` yourself, and don't run `docker compose down` either — the workflow tears it down after you finish, whether you succeed or not. Skip straight to step 7.

**Interactive:** resolve the image tag from the PR's `headRefName` (from step 2). **Also resolve the OS variant** — don't assume alma9 or alma10, check what this PR's own CI actually built:
```bash
gh pr checks <NUM> --repo centreon/centreon | grep -iE "dockerize|slim" 
```
and match the `alma\d+` suffix that appears there (this project's default OS target has changed before; hardcoding one is how a previous version of this file briefly had a wrong note).

```bash
WEB_IMAGE=docker.centreon.com/centreon/centreon-web-slim-<os>:<headRefName> \
  docker compose -f .github/docker/docker-compose.yml up -d --wait
```

Run from the repo root. `--wait` already blocks until Docker Compose's healthcheck passes (it also pulls the image if it isn't present locally — no separate pull/poll loop needed). If the branch's image doesn't exist yet (PR still building in CI), say so and ask whether to wait/retry or fall back to a different tag (e.g. `develop`) — don't silently substitute an unrelated build.

If `--wait` fails or times out, pull logs before giving up:
```bash
docker compose -f .github/docker/docker-compose.yml logs web --tail 100
```

Centreon is then reachable at `http://localhost:4000/centreon`.

## 7. Launch the frontend and log in

Via Playwright MCP: navigate to `http://localhost:4000/centreon/login`, then use the `login` entries from `selectors.json` to authenticate. Default credentials (from `centreon/tests/e2e/fixtures/users/admin.json`): `admin` / `Centreon!2021` — unless the ticket specifically concerns ACL/permissions for a non-admin persona, in which case use the persona it describes instead.

## 8. Execute the Gherkin against the real app

For each `Given`/`When`/`Then` step, drive Playwright MCP directly (snapshot → click/type/wait using the resolved selectors, or `browser_evaluate` for read-only DOM assertions inside the legacy iframe). Record, per scenario:
- pass/fail,
- for a failure: a screenshot (Playwright's screenshot tool) and the exact mismatch (expected vs. observed),
- anything the ticket/PR claimed that you could *not* actually exercise (e.g. requires data you can't seed) — call this out explicitly rather than skipping it silently.

## 9. Report — and stop

Produce a concise report with:
- Ticket key/title/status, PR link, and any Jira-vs-PR-vs-diff discrepancy from step 3.
- The full Gherkin you wrote (or a link to the saved `.feature` file).
- Pass/fail per scenario, with screenshots for failures.
- Any selectors newly added to the catalog this run.
- A clear verdict — e.g. "Ready to leave QA NEEDED" or "Blocking: <what's broken>" — but **do not transition the Jira ticket**; tell the user what transition you'd recommend and let them do it (or ask you to, explicitly, as a separate action).

**Interactive:** post this as your chat reply. Ask whether to tear the environment down (`docker compose -f .github/docker/docker-compose.yml down`) or leave it running for manual follow-up.

**CI:** don't tear the environment down yourself — per step 6, the workflow's own "Tear down environment" step does that after you finish, success or failure, so it happens even if you error out partway. Just post the report as a Jira comment via REST:
```bash
curl -su "$JIRA_EMAIL:$JIRA_API_TOKEN" -X POST -H "Content-Type: application/json" \
  "$JIRA_BASE_URL/rest/api/3/issue/$TICKET_KEY/comment" \
  --data "$(jq -n --arg text "$REPORT_MARKDOWN" '{body:{type:"doc",version:1,content:[{type:"paragraph",content:[{type:"text",text:$text}]}]}}')"
```
(Jira Cloud comments are ADF, not Markdown — a single text block is fine for a first version; a nicer ADF structure with headings/tables can come later.) Do this even if the run found blocking issues — silence is worse than a "found problems" comment. If something failed hard enough that you can't produce a real report (environment never came up, PR not found, etc.), still post a short comment saying so with a link to the workflow run logs, rather than leaving the ticket untouched.
