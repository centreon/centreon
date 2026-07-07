// Provision Xray Tests for e2e scenarios that carry no Jira-key tag: create a Cucumber
// Test for each and inject `@<KEY>` (e.g. @MON-12345) above the scenario. Idempotent —
// a scenario that already has such a tag is skipped, so a re-run only covers what is
// missing (and is the fallback if a create fails). Scenarios (or whole features) tagged
// @ignore are skipped too: they never run, so they get no Xray Test.
//
// Required env: FEATURES_DIR, XRAY_TOKEN (unless DRY_RUN=true)
// Optional env: PROJECT_KEY (default MON), DRY_RUN (default false)

const fs = require("node:fs");
const path = require("node:path");

const XRAY_GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";
const SCENARIO_REGEX = /^(\s*)(?:Scenario|Scenario Outline):\s*(.+?)\s*$/;
const FEATURE_REGEX = /^\s*Feature:/;
const BLOCK_END_REGEX = /^\s*(?:@|Scenario|Feature|Rule|Background)\b/;
// A Jira issue-key tag used as an Xray Test reference, e.g. @MON-12345.
const JIRA_PROJECT_KEY_TAG_REGEX = /@[A-Z]+-\d/;
const IGNORE_TAG_REGEX = /@ignore\b/;

const featuresDir = process.env.FEATURES_DIR;
const xrayToken = process.env.XRAY_TOKEN;
const projectKey = process.env.PROJECT_KEY || "MON";
const dryRun = process.env.DRY_RUN === "true";

if (!featuresDir) {
  throw new Error("missing FEATURES_DIR");
}

const createTestMutation = fs.readFileSync(path.join(__dirname, "create-test.graphql"), "utf8");

// Create one Cucumber Test in Xray and return its Jira key (e.g. "MON-12345").
async function createTest(summary, gherkin) {
  const response = await fetch(XRAY_GRAPHQL_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${xrayToken}`,
    },
    body: JSON.stringify({
      query: createTestMutation,
      variables: { projectKey, summary, gherkin },
    }),
  });

  const result = await response.json();
  const key = result.data?.createTest?.test?.jira?.key;
  if (!key) {
    throw new Error(`createTest failed for "${summary}": ${JSON.stringify(result.errors ?? result)}`);
  }
  return key;
}

// Every *.feature file under a directory, recursively.
function listFeatureFiles(directory) {
  const files = [];
  for (const dirEntry of fs.readdirSync(directory, { withFileTypes: true })) {
    const entryPath = path.join(directory, dirEntry.name);
    if (dirEntry.isDirectory()) {
      files.push(...listFeatureFiles(entryPath));
    } else if (dirEntry.name.endsWith(".feature")) {
      files.push(entryPath);
    }
  }
  return files;
}

// The contiguous tag lines directly above the line at `lineIndex`.
function tagLinesAbove(lines, lineIndex) {
  let firstTagIndex = lineIndex;
  while (firstTagIndex > 0 && lines[firstTagIndex - 1].trim().startsWith("@")) {
    firstTagIndex--;
  }
  return lines.slice(firstTagIndex, lineIndex);
}

const hasJiraKeyTag = (lines, lineIndex) =>
  tagLinesAbove(lines, lineIndex).some((line) => JIRA_PROJECT_KEY_TAG_REGEX.test(line));

const isIgnored = (lines, lineIndex) =>
  tagLinesAbove(lines, lineIndex).some((line) => IGNORE_TAG_REGEX.test(line));

// The scenario's gherkin: its line down to the next scenario / tag / feature / EOF.
function scenarioGherkin(lines, scenarioIndex) {
  let blockEnd = scenarioIndex + 1;
  while (blockEnd < lines.length && !BLOCK_END_REGEX.test(lines[blockEnd])) {
    blockEnd++;
  }
  return lines.slice(scenarioIndex, blockEnd).join("\n");
}

// Create the missing Tests in one feature file and inject their tags. Walks bottom-up
// so inserting a tag never shifts a scenario line still to visit. Returns the count.
async function provisionFeatureFile(featureFile) {
  const lines = fs.readFileSync(featureFile, "utf8").split("\n");
  const shortPath = path.relative(featuresDir, featureFile);

  // Skip the whole file when the Feature itself is @ignore'd.
  const featureIndex = lines.findIndex((line) => FEATURE_REGEX.test(line));
  if (featureIndex !== -1 && isIgnored(lines, featureIndex)) {
    return 0;
  }

  let created = 0;
  for (let index = lines.length - 1; index >= 0; index--) {
    const match = lines[index].match(SCENARIO_REGEX);
    if (!match || hasJiraKeyTag(lines, index) || isIgnored(lines, index)) {
      continue;
    }

    const [, indent, name] = match;
    if (dryRun) {
      console.log(`[dry-run] ${shortPath}:${index + 1}  ${name}`);
    } else {
      const key = await createTest(name, scenarioGherkin(lines, index));
      lines.splice(index, 0, `${indent}@${key}`);
      console.log(`${shortPath}  ${key} <- ${name}`);
    }
    created++;
  }

  if (created > 0 && !dryRun) {
    fs.writeFileSync(featureFile, lines.join("\n"));
  }
  return created;
}

async function main() {
  const featureFiles = listFeatureFiles(featuresDir);
  console.log(`Scanning ${featureFiles.length} feature file(s)${dryRun ? " [dry-run]" : ""}.`);

  let total = 0;
  for (const featureFile of featureFiles) {
    total += await provisionFeatureFile(featureFile);
  }
  console.log(`Done: ${total} test(s) ${dryRun ? "to create" : "created + tagged"}.`);
}

main().catch((error) => {
  console.error(`ERROR: ${error.message}`);
  process.exit(1);
});
