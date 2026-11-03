// Sync the Gherkin definition of Xray Tests with the e2e scenarios tagged @<KEY>.
// The Cucumber import links results to existing Tests through their tag but never
// rewrites their definition, so a Test created empty or whose scenario changed stays
// stale. For each tagged scenario not marked @ignore, compare its normalized Gherkin
// with the Test's and rewrite the Test when they differ (switching a non-Gherkin Test
// Type first).
//
// Required env: FEATURES_DIR, XRAY_TOKEN (unless DRY_RUN=true)
// Optional env: PROJECT_KEY (default MON), DRY_RUN, TEST_KEYS,
//               GHERKIN_TEST_TYPE (default Cucumber)
//
// DRY_RUN without XRAY_TOKEN only parses the features (no network call at all).

const fs = require("node:fs");
const path = require("node:path");

const XRAY_GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";
const SCENARIO_REGEX = /^\s*(?:Scenario|Scenario Outline):\s*(.+?)\s*$/;
const FEATURE_REGEX = /^\s*Feature:/;
const BLOCK_END_REGEX = /^\s*(?:@|Scenario|Feature|Rule|Background)\b/;
const IGNORE_TAG_REGEX = /@ignore\b/;
const BATCH_SIZE = 100;
const MAX_HTTP_RETRIES = 5;
const RETRY_DELAY_MS = 2000;

const {
  FEATURES_DIR,
  XRAY_TOKEN = "",
  PROJECT_KEY = "MON",
  DRY_RUN = "false",
  TEST_KEYS = "",
  GHERKIN_TEST_TYPE = "Cucumber",
} = process.env;

const dryRun = DRY_RUN === "true";
const keyTagRegex = () => new RegExp(`@(${PROJECT_KEY}-\\d+)\\b`, "g");
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Dedent, strip trailing spaces and surrounding blank lines, LF line endings.
function normalizeGherkin(text) {
  const lines = (text || "").split(/\r?\n/).map((line) => line.replace(/\s+$/, ""));
  while (lines.length > 0 && lines[0] === "") lines.shift();
  while (lines.length > 0 && lines[lines.length - 1] === "") lines.pop();
  const indents = lines.filter((line) => line !== "").map((line) => line.match(/^\s*/)[0].length);
  const commonIndent = indents.length > 0 ? Math.min(...indents) : 0;
  return lines.map((line) => line.slice(commonIndent)).join("\n");
}

// Every *.feature file under a directory, recursively, in a stable order.
function listFeatureFiles(directory) {
  const files = [];
  const entries = fs.readdirSync(directory, { withFileTypes: true });
  entries.sort((a, b) => a.name.localeCompare(b.name));
  for (const dirEntry of entries) {
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

const isIgnored = (lines, lineIndex) =>
  tagLinesAbove(lines, lineIndex).some((line) => IGNORE_TAG_REGEX.test(line));

// The scenario block: its line down to the next tag / scenario / feature / rule /
// background / EOF (Examples included).
function scenarioBlock(lines, scenarioIndex) {
  let blockEnd = scenarioIndex + 1;
  while (blockEnd < lines.length && !BLOCK_END_REGEX.test(lines[blockEnd])) {
    blockEnd++;
  }
  return lines.slice(scenarioIndex, blockEnd).join("\n");
}

// Tagged scenarios of every feature file: [{ key, name, gherkin, location }].
function extractTaggedScenarios(featuresDir) {
  const scenarios = [];
  let ignoredCount = 0;
  for (const featureFile of listFeatureFiles(featuresDir)) {
    const lines = fs.readFileSync(featureFile, "utf8").split(/\r?\n/);
    const shortPath = path.relative(featuresDir, featureFile);
    const featureIndex = lines.findIndex((line) => FEATURE_REGEX.test(line));
    const featureIgnored = featureIndex !== -1 && isIgnored(lines, featureIndex);

    lines.forEach((line, index) => {
      const match = line.match(SCENARIO_REGEX);
      if (!match) return;
      const tags = tagLinesAbove(lines, index).join(" ");
      const keys = [...new Set([...tags.matchAll(keyTagRegex())].map((m) => m[1]))];
      if (keys.length === 0) return;
      if (featureIgnored || isIgnored(lines, index)) {
        ignoredCount++;
        return;
      }
      const gherkin = normalizeGherkin(scenarioBlock(lines, index));
      for (const key of keys) {
        scenarios.push({ key, name: match[1], gherkin, location: `${shortPath}:${index + 1}` });
      }
    });
  }
  return { scenarios, ignoredCount };
}

// Group scenarios by key; a key shared by scenarios with different names or bodies is
// a conflict (writing either one would flip-flop the Test).
function groupByKey(scenarios) {
  const byKey = new Map();
  for (const scenario of scenarios) {
    if (!byKey.has(scenario.key)) byKey.set(scenario.key, []);
    byKey.get(scenario.key).push(scenario);
  }
  const resolved = new Map();
  const conflicts = new Map();
  for (const [key, entries] of byKey) {
    const names = new Set(entries.map((entry) => entry.name));
    const bodies = new Set(entries.map((entry) => entry.gherkin));
    if (names.size > 1 || bodies.size > 1) {
      conflicts.set(key, entries);
    } else {
      resolved.set(key, entries[0]);
    }
  }
  return { resolved, conflicts };
}

function loadGraphqlOperation(operationName) {
  return fs.readFileSync(path.join(__dirname, `${operationName}.graphql`), "utf8");
}

// Xray GraphQL call with retry on transient failures. Throws on GraphQL errors.
async function callXrayGraphql(graphqlQuery, queryVariables) {
  for (let attempt = 0; ; attempt++) {
    let response = null;
    let networkError = null;
    try {
      response = await fetch(XRAY_GRAPHQL_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${XRAY_TOKEN}` },
        body: JSON.stringify({ query: graphqlQuery, variables: queryVariables }),
      });
    } catch (error) {
      networkError = error;
    }
    const status = networkError ? null : response.status;
    const isTransient = status === null || status === 429 || status >= 500;
    if (!isTransient || attempt >= MAX_HTTP_RETRIES) {
      if (networkError) throw networkError;
      const responseJson = await response.json();
      if (responseJson.errors && responseJson.errors.length > 0) {
        throw new Error(`Xray GraphQL error: ${JSON.stringify(responseJson.errors)}`);
      }
      return responseJson;
    }
    console.log(`  Xray GraphQL: transient failure, retry #${attempt + 1} in ${RETRY_DELAY_MS}ms`);
    await sleep(RETRY_DELAY_MS);
  }
}

// Current Xray definition of the given keys, by batches: Map key -> { issueId, gherkin, testType }.
async function fetchXrayTests(keys) {
  const query = loadGraphqlOperation("get-tests");
  const tests = new Map();
  for (let index = 0; index < keys.length; index += BATCH_SIZE) {
    const batch = keys.slice(index, index + BATCH_SIZE);
    const jql = `key in (${batch.join(",")})`;
    for (let start = 0; ; start += BATCH_SIZE) {
      const json = await callXrayGraphql(query, { jql, limit: BATCH_SIZE, start });
      const page = json.data?.getTests;
      for (const test of page?.results || []) {
        if (test?.jira?.key) tests.set(test.jira.key, test);
      }
      if (start + BATCH_SIZE >= (page?.total || 0)) break;
    }
  }
  return tests;
}

// Switch a non-Gherkin Test to the Gherkin Test Type, retrying once when it does not stick.
async function ensureGherkinTestType(issueId) {
  const mutation = loadGraphqlOperation("update-test-type");
  for (let attempt = 1; attempt <= 2; attempt++) {
    const json = await callXrayGraphql(mutation, { id: issueId, t: GHERKIN_TEST_TYPE });
    if (json.data?.updateTestType?.testType?.kind === "Gherkin") return;
    console.log(`  test type did not switch to ${GHERKIN_TEST_TYPE} (attempt ${attempt}/2)`);
  }
  throw new Error(`test type did not switch to ${GHERKIN_TEST_TYPE}`);
}

function writeStepSummary(markdown) {
  if (process.env.GITHUB_STEP_SUMMARY) {
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${markdown}\n`);
  }
}

const keyList = (keys) => (keys.length > 0 ? keys.map((key) => `\`${key}\``).join(", ") : "-");

async function main() {
  if (!FEATURES_DIR) throw new Error("missing FEATURES_DIR");
  if (!dryRun && !XRAY_TOKEN) throw new Error("missing XRAY_TOKEN (required unless DRY_RUN=true)");

  const { scenarios, ignoredCount } = extractTaggedScenarios(FEATURES_DIR);
  const { resolved, conflicts } = groupByKey(scenarios);
  const keyFilter = new Set(TEST_KEYS.split(/[\s,]+/).filter(Boolean));
  const targetKeys = [...resolved.keys()].filter((key) => keyFilter.size === 0 || keyFilter.has(key)).sort();
  const conflictKeys = [...conflicts.keys()].filter((key) => keyFilter.size === 0 || keyFilter.has(key)).sort();

  console.log(
    `Found ${scenarios.length} tagged scenario(s), ${resolved.size + conflicts.size} key(s), ` +
      `${conflicts.size} conflicting key(s), ${ignoredCount} @ignore scenario(s) skipped` +
      `${dryRun ? " [dry-run]" : ""}.`,
  );
  for (const key of conflictKeys) {
    console.log(`WARNING: ${key} is set on different scenarios, not synced:`);
    for (const entry of conflicts.get(key)) console.log(`  ${entry.location}  ${entry.name}`);
  }

  const summary = [
    "### Xray Gherkin sync" + (dryRun ? " (dry-run)" : ""),
    "",
    `- Tagged scenarios: ${scenarios.length} (${ignoredCount} @ignore skipped)`,
    `- Keys in scope: ${targetKeys.length}`,
    `- Conflicting keys (not synced): ${conflictKeys.length} ${keyList(conflictKeys)}`,
  ];

  if (!XRAY_TOKEN) {
    console.log("No XRAY_TOKEN: Xray comparison skipped.");
    summary.push("- Xray comparison skipped (no token)");
    writeStepSummary(summary.join("\n"));
    return;
  }

  const xrayTests = await fetchXrayTests(targetKeys);
  const missingKeys = targetKeys.filter((key) => !xrayTests.has(key));
  const upToDateKeys = [];
  const outdatedKeys = [];
  for (const key of targetKeys) {
    const test = xrayTests.get(key);
    if (!test) continue;
    if (normalizeGherkin(test.gherkin) === resolved.get(key).gherkin) {
      upToDateKeys.push(key);
    } else {
      outdatedKeys.push(key);
    }
  }

  const syncedKeys = [];
  const failedKeys = [];
  for (const key of outdatedKeys) {
    const test = xrayTests.get(key);
    const scenario = resolved.get(key);
    const typeNote = test.testType?.kind === "Gherkin" ? "" : ` (type ${test.testType?.name} -> ${GHERKIN_TEST_TYPE})`;
    if (dryRun) {
      console.log(`[dry-run] ${key} outdated${typeNote}  <- ${scenario.location}`);
      syncedKeys.push(key);
      continue;
    }
    try {
      if (test.testType?.kind !== "Gherkin") await ensureGherkinTestType(test.issueId);
      await callXrayGraphql(loadGraphqlOperation("update-gherkin"), { id: test.issueId, gherkin: scenario.gherkin });
      console.log(`${key} synced${typeNote}  <- ${scenario.location}`);
      syncedKeys.push(key);
    } catch (error) {
      console.log(`WARNING: ${key} not synced: ${error.message}`);
      failedKeys.push(key);
    }
  }

  summary.push(
    `- Up to date: ${upToDateKeys.length}`,
    `- ${dryRun ? "To resync" : "Resynced"}: ${syncedKeys.length} ${keyList(syncedKeys)}`,
    `- Not found in Xray: ${missingKeys.length} ${keyList(missingKeys)}`,
  );
  if (failedKeys.length > 0) summary.push(`- Failed: ${failedKeys.length} ${keyList(failedKeys)}`);
  writeStepSummary(summary.join("\n"));
  console.log(summary.slice(2).join("\n"));

  if (failedKeys.length > 0) process.exit(1);
}

if (require.main === module) {
  main().catch((error) => {
    console.error(`ERROR: ${error.message}`);
    process.exit(1);
  });
}

module.exports = { normalizeGherkin, extractTaggedScenarios, groupByKey };
