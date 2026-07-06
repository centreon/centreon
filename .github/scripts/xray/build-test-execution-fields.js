// Build the Jira "info" payload (issueJiraFields.json) used by the Xray import
// multipart endpoint to create/update the Test Execution issue.
//
// Replaces an inline printf in xray-api-import-results.yml: building the JSON with
// JSON.stringify removes all the manual quoting/escaping hazards, and keeps the
// summary/description templates in one readable place.
//
// All values come from environment variables (set by the workflow step):
//   NORMALIZED_TYPE_TEST_EXECUTION, NORMALIZED_TYPE_ENVIRONMENT, NORMALIZED_COMPONENT_NAME,
//   NORMALIZED_OPERATING_SYSTEM, NORMALIZED_DATABASE_NAME, NORMALIZED_BROWSER,
//   MAJOR_VERSION, MINOR_VERSION, MODULE_NAME, TEST_PLAN_KEY, WORKFLOW_RUN_URL
// Args: <output-file>
const fs = require("node:fs");

const outputFile = process.argv[2];
if (!outputFile) {
  console.error("Usage: node build-test-execution-fields.js <output-file>");
  process.exit(1);
}

const env = process.env;
const version = `${env.MAJOR_VERSION}.${env.MINOR_VERSION}`;

// Run type mirrors the Test Plan naming (jira-generate-summary): scheduled runs are
// NIGHTLY, hotfix/release branches HOTFIX/RELEASE, everything else COMMON.
const ref = env.GITHUB_REF_NAME || "";
const runType =
  env.GITHUB_EVENT_NAME === "schedule"
    ? "NIGHTLY"
    : ref.startsWith("hotfix")
      ? "HOTFIX"
      : ref.startsWith("release")
        ? "RELEASE"
        : "COMMON";

// Test Execution title (the run type is surfaced right after the [TYPE] tag).
const summary =
  `[${env.NORMALIZED_TYPE_TEST_EXECUTION}] ${runType} ${env.NORMALIZED_TYPE_ENVIRONMENT} ` +
  `${env.NORMALIZED_COMPONENT_NAME} ${version} ` +
  `(${env.NORMALIZED_OPERATING_SYSTEM} + ${env.NORMALIZED_DATABASE_NAME})`;

// Description: every useful piece of run context, one per line (empty values dropped).
const descriptionFields = [
  ["Component", env.NORMALIZED_COMPONENT_NAME],
  ["Version", version],
  ["Run type", runType],
  ["Environment", env.NORMALIZED_TYPE_ENVIRONMENT],
  ["Operating system", env.NORMALIZED_OPERATING_SYSTEM],
  ["Database", env.NORMALIZED_DATABASE_NAME],
  ["Browser", env.NORMALIZED_BROWSER],
  ["Test Plan", env.TEST_PLAN_KEY],
  ["Commit", env.GITHUB_SHA],
  ["Workflow run", env.WORKFLOW_RUN_URL],
];
const description =
  `Automated ${env.NORMALIZED_TYPE_TEST_EXECUTION} test execution (${runType}).\n\n${descriptionFields
    .filter(([, value]) => value)
    .map(([label, value]) => `- ${label}: ${value}`)
    .join("\n")}`;

// Environments reported to Xray (drop any that are empty so we never send blanks).
// Each value must be a declared Test Environment in the Xray project, or the import 400s.
const environments = [
  runType,
  env.NORMALIZED_TYPE_ENVIRONMENT,
  env.NORMALIZED_OPERATING_SYSTEM,
  env.NORMALIZED_DATABASE_NAME,
  env.NORMALIZED_BROWSER,
].filter(Boolean);

// CI service account (devsecops service), matching the legacy flow so automated
// Test Executions are attributed instead of showing as Unassigned.
const assigneeAccountId =
  env.TEST_EXECUTION_ASSIGNEE_ID || "712020:093f82f0-b0f1-4498-8369-fbe72fb50bcb";

const payload = {
  fields: {
    project: { key: "MON" },
    summary,
    issuetype: { name: "Test Execution" },
    assignee: { id: assigneeAccountId },
    components: [{ name: env.MODULE_NAME }],
    description,
    priority: { name: "Low" },
    labels: ["automated-tests", "github-actions"],
  },
  xrayFields: {
    testPlanKey: env.TEST_PLAN_KEY,
    environments,
  },
};

fs.writeFileSync(outputFile, `${JSON.stringify(payload, null, 2)}\n`);
console.log(`Wrote Test Execution fields -> ${outputFile}`);
console.log(`  summary: ${summary}`);
