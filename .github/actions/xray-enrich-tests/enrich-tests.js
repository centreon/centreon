// Enrich Xray Test issues found from a Test Execution, a Test Plan, or a JQL query.
// For each test: optionally set the Xray Test Type (GraphQL), the Jira component
// and labels (Jira REST), and walk the workflow to Resolved. When the source is a
// Test Execution, the Test Execution issue itself is resolved too (not just its Tests).
//
// Required env: SOURCE_TYPE, SOURCE_VALUE, XRAY_TOKEN, JIRA_USER_EMAIL, JIRA_TOKEN
// Optional env: COMPONENT, LABELS, TEST_TYPE, RESOLVE
//
// Uses Node's global fetch (Node 20+) so it stays dependency-free, like merge-junit.js.

const fs = require("node:fs");
const path = require("node:path");

const XRAY_GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";
const JIRA_REST_BASE_URL = "https://centreon.atlassian.net/rest/api/2";
// Known Test resolution chain (Open Request -> Ready for implementation -> Start ->
// Ready for review -> Resolved). Preferred when available; the walk falls back to any
// transition leading to a done / in-progress status, so it also resolves the Test
// Execution issue type (whose transition ids differ).
const TEST_RESOLVE_TRANSITION_IDS = ["61", "81", "21", "31"];
const MAX_TRANSITION_HOPS = 8;
// Transition names that are dead-ends or regressions — never auto-follow them when
// walking to a resolved/done status. The Test Execution workflow exposes a *global*
// BLOCKED transition (available from every status) that would otherwise trap the walk.
const TRAP_TRANSITION_NAME = /blocked|waiting|back to|reject|cancel/i;
const PAGE_SIZE = 100;

// Test Execution resolution paths (named transitions). A passing run is Closed; a run
// with failures is routed to the dedicated "Execution failed" status so it stays
// visible in the campaign instead of being closed as if everything was fine.
const TE_PASS_PATH = ["Ready to execute", "Start execution", "End execution", "Close"];
const TE_FAIL_PATH = ["Ready to execute", "Start execution", "Execution to failed"];
const TE_PASS_TARGET_STATUS = "Closed";
const TE_FAIL_TARGET_STATUS = "Execution failed";
// Xray test-run statuses that mark the execution as failed.
const FAILED_RUN_STATUSES = new Set(["FAILED", "ABORTED"]);
// Run statuses that mean "not finished" — forced to ABORTED after import so a crashed
// job leaves no lingering EXECUTING/TO DO run on the Test Execution.
const UNFINISHED_RUN_STATUSES = new Set(["TODO", "TO DO", "EXECUTING"]);

const {
  SOURCE_TYPE,
  SOURCE_VALUE,
  XRAY_TOKEN,
  JIRA_USER_EMAIL,
  JIRA_TOKEN,
  COMPONENT = "",
  LABELS = "",
  TEST_TYPE = "",
  RESOLVE = "false",
} = process.env;

for (const [variableName, variableValue] of Object.entries({
  SOURCE_TYPE,
  SOURCE_VALUE,
  XRAY_TOKEN,
  JIRA_USER_EMAIL,
  JIRA_TOKEN,
})) {
  if (!variableValue) {
    console.error(`ERROR: missing required environment variable ${variableName}`);
    process.exit(1);
  }
}

const jiraAuthorizationHeader = `Basic ${Buffer.from(`${JIRA_USER_EMAIL}:${JIRA_TOKEN}`).toString("base64")}`;

const MAX_HTTP_RETRIES = 5;
const RETRY_DELAY_MS = 2000;
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Backoff before retrying a transient failure. `status` is null on a network error.
// Return the delay in ms, or null to stop retrying.
function retryDelayMs(attempt, status) {
  if (attempt >= MAX_HTTP_RETRIES) return null;
  const isTransient = status === null || status === 429 || status >= 500;
  if (!isTransient) return null;
  return RETRY_DELAY_MS;
}

// Fetch with retry/backoff on transient failures (policy in retryDelayMs).
async function fetchWithRetry(operation, label) {
  for (let attempt = 0; ; attempt++) {
    let response = null;
    let networkError = null;
    try {
      response = await operation();
      if (response.ok) return response;
    } catch (error) {
      networkError = error;
    }
    const delay = retryDelayMs(attempt, networkError ? null : response.status);
    if (delay == null) {
      if (networkError) throw networkError;
      return response;
    }
    console.log(`  ${label}: transient failure, retry #${attempt + 1} in ${delay}ms`);
    await sleep(delay);
  }
}

// Jira REST call -> { ok, status, body } where body is parsed JSON or raw text.
async function callJiraRest(httpMethod, endpointPath, requestPayload) {
  const response = await fetchWithRetry(
    () =>
      fetch(`${JIRA_REST_BASE_URL}${endpointPath}`, {
        method: httpMethod,
        headers: {
          Authorization: jiraAuthorizationHeader,
          Accept: "application/json",
          ...(requestPayload ? { "Content-Type": "application/json" } : {}),
        },
        ...(requestPayload ? { body: JSON.stringify(requestPayload) } : {}),
      }),
    `${httpMethod} ${endpointPath}`,
  );
  const responseText = await response.text();
  let parsedBody;
  try {
    parsedBody = responseText ? JSON.parse(responseText) : {};
  } catch {
    parsedBody = responseText;
  }
  return { ok: response.ok, status: response.status, body: parsedBody };
}

// Load a GraphQL document from a .graphql file next to this script, mirroring the
// xray-api-graphql convention (one operation per file) so queries stay out of the code.
function loadGraphqlOperation(operationName) {
  return fs.readFileSync(path.join(__dirname, `${operationName}.graphql`), "utf8");
}

// Xray GraphQL call -> parsed JSON. Throws on a GraphQL-level error array.
async function callXrayGraphql(graphqlQuery, queryVariables) {
  const response = await fetchWithRetry(
    () =>
      fetch(XRAY_GRAPHQL_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${XRAY_TOKEN}`,
        },
        body: JSON.stringify({ query: graphqlQuery, variables: queryVariables }),
      }),
    "Xray GraphQL",
  );
  const responseJson = await response.json();
  if (responseJson.errors && responseJson.errors.length > 0) {
    throw new Error(`Xray GraphQL error: ${JSON.stringify(responseJson.errors)}`);
  }
  return responseJson;
}

async function resolveIssueId(issueKey) {
  const { ok, status, body } = await callJiraRest("GET", `/issue/${issueKey}?fields=id`);
  if (!ok) throw new Error(`cannot resolve issue id for ${issueKey} (HTTP ${status})`);
  return body.id;
}

// Drive an issue toward a "done"-category status, logging each hop as the resulting
// status "<id> - <name>". At each step it prefers the known chain, then a transition
// landing directly in a done status, then the first transition that is NOT a trap
// (BLOCKED / WAITING * / BACK TO *). Stops when done or only traps remain. This walks
// the Test Execution workflow (Open Request -> Ready to execute -> In execution ->
// Execution completed -> Close) without hardcoding its ids.
async function driveToResolved(issueKey, preferredTransitionIds) {
  let status;
  for (let hop = 0; hop < MAX_TRANSITION_HOPS; hop++) {
    const { body } = await callJiraRest("GET", `/issue/${issueKey}?fields=status&expand=transitions`);
    status = body.fields?.status;
    if (status?.statusCategory?.key === "done") break;

    const available = body.transitions || [];
    const nextTransition =
      available.find((t) => preferredTransitionIds.includes(t.id)) ||
      available.find((t) => t.to?.statusCategory?.key === "done") ||
      available.find((t) => !TRAP_TRANSITION_NAME.test(t.name));
    if (!nextTransition) break;

    const { ok } = await callJiraRest("POST", `/issue/${issueKey}/transitions`, {
      transition: { id: nextTransition.id },
    });
    if (!ok) break;
    status = nextTransition.to;
    console.log(`  transition ${nextTransition.id} (${nextTransition.name}) -> status ${status?.id} - ${status?.name}`);
  }
  return status;
}

// Accumulate every page. `fetchPage(pageStart)` must return { items, total };
// we stop once we have walked past the reported total.
async function fetchAllPages(fetchPage) {
  const allItems = [];
  for (let pageStart = 0; ; pageStart += PAGE_SIZE) {
    const { items, total } = await fetchPage(pageStart);
    allItems.push(...items);
    if (pageStart + PAGE_SIZE >= total) break;
  }
  return allItems;
}

// Tests belonging to a Test Plan / Test Execution, read through Xray GraphQL.
async function collectTestsFromContainer() {
  const graphqlEntity = SOURCE_TYPE === "test_execution" ? "getTestExecution" : "getTestPlan";
  const graphqlOperation =
    SOURCE_TYPE === "test_execution" ? "get-test-execution-tests" : "get-test-plan-tests";
  const containerIssueId = /^[A-Z]+-[0-9]+$/.test(SOURCE_VALUE)
    ? await resolveIssueId(SOURCE_VALUE)
    : SOURCE_VALUE;
  const graphqlQuery = loadGraphqlOperation(graphqlOperation);

  return fetchAllPages(async (pageStart) => {
    const responseJson = await callXrayGraphql(graphqlQuery, { id: containerIssueId, start: pageStart });
    const testsPage = responseJson.data[graphqlEntity].tests;
    return {
      // Xray may return null entries (a just-imported test not yet fully indexed) —
      // drop them so the enrich never crashes on eventual consistency.
      items: (testsPage.results || [])
        .filter((testResult) => testResult && testResult.issueId)
        .map((testResult) => ({
          issueId: testResult.issueId,
          issueKey: testResult.jira?.key,
          testTypeName: testResult.testType?.name,
          componentNames: (testResult.jira?.components || []).map((component) => component.name),
          statusCategoryKey: testResult.jira?.status?.statusCategory?.key,
        })),
      total: testsPage.total || 0,
    };
  });
}

// Tests matching a JQL query, read through the Jira REST search endpoint.
async function collectTestsFromJql() {
  return fetchAllPages(async (pageStart) => {
    const encodedJql = encodeURIComponent(SOURCE_VALUE);
    const { ok, status, body } = await callJiraRest(
      "GET",
      `/search?jql=${encodedJql}&fields=id,key&maxResults=${PAGE_SIZE}&startAt=${pageStart}`,
    );
    if (!ok) throw new Error(`JQL search failed (HTTP ${status}): ${JSON.stringify(body)}`);
    return {
      items: (body.issues || []).map((issue) => ({ issueId: issue.id, issueKey: issue.key })),
      total: body.total || 0,
    };
  });
}

// Collect the target tests as [{ issueId, issueKey }], by source type.
async function collectTests() {
  if (SOURCE_TYPE === "test_plan" || SOURCE_TYPE === "test_execution") return collectTestsFromContainer();
  if (SOURCE_TYPE === "jql") return collectTestsFromJql();
  throw new Error(`invalid SOURCE_TYPE '${SOURCE_TYPE}' (expected test_execution|test_plan|jql)`);
}

// Build the Jira REST payload (shape { fields, update }) for PUT /issue/{key}.
// Components are REPLACED (single owning module per test), while labels are ADDED
// without dropping existing ones. Each branch is omitted when its input is empty,
// so we never wipe a field by accident on a partial enrich.
function buildFieldsUpdatePayload(componentName, commaSeparatedLabels) {
  const fieldsUpdatePayload = { fields: {}, update: {} };

  if (componentName) {
    fieldsUpdatePayload.fields.components = [{ name: componentName }];
  }

  const labelAddOperations = commaSeparatedLabels
    .split(",")
    .map((label) => label.trim())
    .filter((label) => label.length > 0)
    .map((label) => ({ add: label }));
  if (labelAddOperations.length > 0) {
    fieldsUpdatePayload.update.labels = labelAddOperations;
  }

  return fieldsUpdatePayload;
}

// Set the Xray Test Type, then VERIFY it actually applied and retry once. Xray can
// silently drop the change on a just-imported test (the type stays "Generic"), so a
// blind updateTestType is not enough. Logs the resulting type; never throws.
async function setTestTypeWithRetry(issueId, issueKey, desiredType) {
  for (let attempt = 1; attempt <= 2; attempt++) {
    try {
      await callXrayGraphql(loadGraphqlOperation("update-test-type"), { id: issueId, t: desiredType });
      const check = await callXrayGraphql(loadGraphqlOperation("get-test-type"), { id: issueId });
      const currentType = check.data?.getTest?.testType?.name;
      if (currentType === desiredType) {
        console.log(`  test type -> ${currentType}`);
        return;
      }
      console.log(`  test type still '${currentType}' (attempt ${attempt}/2)`);
    } catch (error) {
      console.log(`  test type attempt ${attempt}/2 failed: ${error.message}`);
    }
  }
  console.log(`WARNING: test type for ${issueKey} did not stick to ${desiredType}`);
}

// Walk the Test Execution's runs: force any unfinished run (TO DO/EXECUTING) to
// ABORTED so a crashed job leaves no lingering "in progress" run, and report whether
// the execution has failures (any FAILED or ABORTED run). One pass over the runs.
async function finalizeTestExecutionRuns(testExecutionKey) {
  const issueId = /^[A-Z]+-[0-9]+$/.test(testExecutionKey)
    ? await resolveIssueId(testExecutionKey)
    : testExecutionKey;
  const listQuery = loadGraphqlOperation("get-test-execution-results");
  const abortMutation = loadGraphqlOperation("update-test-run-status");
  let hasFailures = false;
  let abortedCount = 0;
  for (let start = 0; ; start += PAGE_SIZE) {
    const json = await callXrayGraphql(listQuery, { id: issueId, start });
    const runsPage = json.data?.getTestExecution?.testRuns;
    const results = runsPage?.results || [];
    for (const run of results) {
      const statusName = (run?.status?.name || "").toUpperCase();
      if (UNFINISHED_RUN_STATUSES.has(statusName)) {
        await callXrayGraphql(abortMutation, { id: run.id, status: "ABORTED" });
        abortedCount++;
        hasFailures = true;
      } else if (FAILED_RUN_STATUSES.has(statusName)) {
        hasFailures = true;
      }
    }
    if (start + PAGE_SIZE >= (runsPage?.total || 0)) break;
  }
  if (abortedCount > 0) console.log(`  marked ${abortedCount} unfinished run(s) as ABORTED`);
  return hasFailures;
}

// Drive the Test Execution to its terminal status by following the named transition
// path for the outcome: Closed when everything passed, "Execution failed" otherwise.
async function resolveTestExecution(testExecutionKey, hasFailures) {
  const allowedTransitionNames = hasFailures ? TE_FAIL_PATH : TE_PASS_PATH;
  const targetStatusName = hasFailures ? TE_FAIL_TARGET_STATUS : TE_PASS_TARGET_STATUS;
  let status;
  for (let hop = 0; hop < MAX_TRANSITION_HOPS; hop++) {
    const { body } = await callJiraRest("GET", `/issue/${testExecutionKey}?fields=status&expand=transitions`);
    status = body.fields?.status;
    if (status?.name === targetStatusName) break;
    const nextTransition = (body.transitions || []).find((t) => allowedTransitionNames.includes(t.name));
    if (!nextTransition) break;
    const { ok } = await callJiraRest("POST", `/issue/${testExecutionKey}/transitions`, {
      transition: { id: nextTransition.id },
    });
    if (!ok) break;
    status = nextTransition.to;
    console.log(`  transition ${nextTransition.id} (${nextTransition.name}) -> status ${status?.id} - ${status?.name}`);
  }
  return status;
}

// True when the test already carries every requested enrichment, so the (idempotent
// but slow) writes can be skipped on repeat runs. Uses the state read with the test
// list — only available on the GraphQL path; JQL-sourced tests lack it and are always
// re-enriched. Labels are not checked individually (they are added together with the
// component on the first enrich).
function isAlreadyEnriched(test) {
  const typeOk = !TEST_TYPE || test.testTypeName === TEST_TYPE;
  const componentOk = !COMPONENT || (test.componentNames || []).includes(COMPONENT);
  const resolvedOk = RESOLVE !== "true" || test.statusCategoryKey === "done";
  return typeOk && componentOk && resolvedOk;
}

async function main() {
  const tests = await collectTests();
  console.log(`Found ${tests.length} test(s) to enrich from ${SOURCE_TYPE} '${SOURCE_VALUE}'.`);
  if (tests.length === 0) {
    console.log("Nothing to enrich.");
    return;
  }

  let failureCount = 0;
  let skippedCount = 0;
  for (const test of tests) {
    const { issueId, issueKey } = test;
    // Skip tests already fully enriched (fast path for repeat runs).
    if (isAlreadyEnriched(test)) {
      skippedCount++;
      continue;
    }
    console.log(`::group::Enrich ${issueKey} (${issueId})`);
    // Isolate each test: a failure here is logged and counted, never aborts the batch.
    let testFailed = false;
    try {
      // 1. Xray Test Type (set + verify + retry once)
      if (TEST_TYPE) {
        await setTestTypeWithRetry(issueId, issueKey, TEST_TYPE);
      }

      // 2. Component and labels (Jira)
      if (COMPONENT || LABELS) {
        const fieldsUpdatePayload = buildFieldsUpdatePayload(COMPONENT, LABELS);
        const { ok, status, body } = await callJiraRest("PUT", `/issue/${issueKey}`, fieldsUpdatePayload);
        if (ok) {
          console.log("  component/labels updated");
        } else {
          console.log(`WARNING: failed to update fields for ${issueKey} (HTTP ${status}): ${JSON.stringify(body)}`);
          testFailed = true;
        }
      }

      // 3. Resolve: walk the known Test chain to a done status.
      if (RESOLVE === "true") {
        const finalStatus = await driveToResolved(issueKey, TEST_RESOLVE_TRANSITION_IDS);
        console.log(`  final status: ${finalStatus?.id} - ${finalStatus?.name}`);
      }
    } catch (error) {
      console.log(`WARNING: enrichment error for ${issueKey}: ${error.message} — will be retried on a re-run`);
      testFailed = true;
    }

    if (testFailed) failureCount++;
    console.log("::endgroup::");
  }

  // Resolve the Test Execution itself (Jira status) — otherwise it stays "Open Request"
  // after import. Closed when all tests passed, "Execution failed" when any failed, so
  // a failing run stays visible. Only when the source IS a Test Execution.
  if (RESOLVE === "true" && SOURCE_TYPE === "test_execution") {
    console.log(`::group::Resolve Test Execution ${SOURCE_VALUE}`);
    const hasFailures = await finalizeTestExecutionRuns(SOURCE_VALUE);
    console.log(`  execution outcome: ${hasFailures ? "FAILURES -> Execution failed" : "all passed -> Closed"}`);
    const testExecutionStatus = await resolveTestExecution(SOURCE_VALUE, hasFailures);
    console.log(`  final status: ${testExecutionStatus?.id} - ${testExecutionStatus?.name}`);
    console.log("::endgroup::");
  }

  const enrichedCount = tests.length - skippedCount - failureCount;
  console.log(
    `Enrichment done: ${enrichedCount} enriched, ${skippedCount} already up-to-date, ${failureCount} left un-enriched.`,
  );
  if (failureCount > 0) {
    console.error(`${failureCount} test(s) left un-enriched — re-run the job to finish (it resumes idempotently).`);
    process.exit(1);
  }
}

main().catch((error) => {
  console.error(`ERROR: ${error.message}`);
  process.exit(1);
});
