// Sync the Gherkin definition of Xray Tests with the e2e scenarios tagged @<KEY>.
// The Cucumber import links results to existing Tests through their tag but never
// rewrites their definition, so a Test created empty or whose scenario changed stays
// stale. For each tagged scenario not marked @ignore, compare its normalized steps
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
const { createXrayGraphqlClient, loadGraphqlOperation: loadGraphqlFile } = require("../xray-common/xray-client");

const SCENARIO_REGEX = /^\s*(?:Scenario|Scenario Outline):\s*(.+?)\s*$/;
const FEATURE_REGEX = /^\s*Feature:/;
const BLOCK_END_REGEX = /^\s*(?:@|Scenario|Feature|Rule|Background)\b/;
const IGNORE_TAG_REGEX = /@ignore\b/;
const BATCH_SIZE = 100;

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

// Xray stores only the scenario steps (and Examples): drop a leading Scenario line and
// comment lines (commented-out code must not reach Xray), dedent, strip trailing spaces
// and surrounding blank lines, LF line endings.
function normalizeGherkin(text) {
  const lines = (text || "")
    .split(/\r?\n/)
    .map((line) => line.replace(/\s+$/, ""))
    .filter((line) => !line.trimStart().startsWith("#"));
  while (lines.length > 0 && lines[0] === "") lines.shift();
  if (lines.length > 0 && SCENARIO_REGEX.test(lines[0])) lines.shift();
  while (lines.length > 0 && lines[0] === "") lines.shift();
  while (lines.length > 0 && lines[lines.length - 1] === "") lines.pop();
  const indents = lines.filter((line) => line !== "").map((line) => line.match(/^\s*/)[0].length);
  const commonIndent = indents.length > 0 ? Math.min(...indents) : 0;
  return lines.map((line) => line.slice(commonIndent)).join("\n");
}

// Comparison form of a normalized definition: Xray re-indents Examples, aligns tables
// and collapses spaces on its side, so layout alone must not trigger a rewrite.
function comparisonKey(gherkin) {
  return gherkin
    .split("\n")
    .map((line) => {
      const trimmed = line.trim().replace(/\s+/g, " ");
      return trimmed.startsWith("|") ? trimmed.split("|").map((cell) => cell.trim()).join("|") : trimmed;
    })
    .filter((line) => line !== "")
    .join("\n");
}

// An Xray definition holding more than one scenario's steps (Feature, Background, Rule,
// tags, several scenarios) would lose content if overwritten: leave it for a human.
function holdsMoreThanSteps(xrayGherkin) {
  const lines = (xrayGherkin || "").split(/\r?\n/).map((line) => line.trim());
  const scenarioLines = lines.filter((line) => SCENARIO_REGEX.test(line)).length;
  return scenarioLines > 1 || lines.some((line) => /^(?:Feature|Background|Rule):/.test(line) || line.startsWith("@"));
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
    const bodies = new Set(entries.map((entry) => comparisonKey(entry.gherkin)));
    if (names.size > 1 || bodies.size > 1) {
      conflicts.set(key, entries);
    } else {
      resolved.set(key, entries[0]);
    }
  }
  return { resolved, conflicts };
}

const loadGraphqlOperation = (operationName) => loadGraphqlFile(__dirname, operationName);
const callXrayGraphql = createXrayGraphqlClient(XRAY_TOKEN);

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

// Jira issue type of tagged keys that are not Xray Tests: Map key -> type name. A key that
// is neither a Test nor a coverable issue (deleted key, typo) is absent from the map.
async function fetchNonTestIssueTypes(keys) {
  const query = loadGraphqlOperation("get-coverable-issues");
  const types = new Map();
  for (let index = 0; index < keys.length; index += BATCH_SIZE) {
    const jql = `key in (${keys.slice(index, index + BATCH_SIZE).join(",")})`;
    for (let start = 0; ; start += BATCH_SIZE) {
      const json = await callXrayGraphql(query, { jql, limit: BATCH_SIZE, start });
      const page = json.data?.getCoverableIssues;
      for (const issue of page?.results || []) {
        if (issue?.jira?.key) types.set(issue.jira.key, issue.jira.issuetype?.name || "unknown type");
      }
      if (start + BATCH_SIZE >= (page?.total || 0)) break;
    }
  }
  return types;
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
    `- Conflicting keys (not synced): ${conflictKeys.length}`,
  ];

  if (!XRAY_TOKEN) {
    console.log("No XRAY_TOKEN: Xray comparison skipped.");
    summary.push("- Xray comparison skipped (no token)");
    writeStepSummary(summary.join("\n"));
    return;
  }

  // Conflicting keys are looked up too, so a ticket key set on several scenarios is still
  // reported; they stay out of the sync below.
  const xrayTests = await fetchXrayTests([...targetKeys, ...conflictKeys]);
  const missingKeys = [...targetKeys, ...conflictKeys].filter((key) => !xrayTests.has(key)).sort();
  // Diagnostic only: a failed lookup must not stop the sync of the known Tests.
  let nonTestTypes = new Map();
  let classificationFailed = false;
  if (missingKeys.length > 0) {
    try {
      nonTestTypes = await fetchNonTestIssueTypes(missingKeys);
    } catch (error) {
      classificationFailed = true;
      console.log(`::warning::Could not check the issue type of keys missing from Xray: ${error.message}`);
    }
  }
  const nonTestKeys = missingKeys.filter((key) => nonTestTypes.has(key));
  const unknownKeys = missingKeys.filter((key) => !nonTestTypes.has(key));
  const locationsOf = (key) => scenarios.filter((scenario) => scenario.key === key).map((scenario) => scenario.location);
  for (const key of nonTestKeys) {
    for (const location of locationsOf(key)) {
      const [file, line] = location.split(":");
      console.log(
        `::warning file=${path.join(FEATURES_DIR, file)},line=${line}::${key} is a ${nonTestTypes.get(key)}, not an Xray Test: tag this scenario with a Test key`,
      );
    }
  }
  const upToDateKeys = [];
  const outdatedKeys = [];
  const protectedKeys = [];
  for (const key of targetKeys) {
    const test = xrayTests.get(key);
    if (!test) continue;
    if (comparisonKey(normalizeGherkin(test.gherkin)) === comparisonKey(resolved.get(key).gherkin)) {
      upToDateKeys.push(key);
    } else if (holdsMoreThanSteps(test.gherkin)) {
      console.log(`WARNING: ${key} holds more than one scenario's steps in Xray, not overwritten`);
      protectedKeys.push(key);
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

  const notFoundLabel = classificationFailed ? "Missing from Xray, type not checked" : "Neither a Test nor a coverable issue";
  const counts = [
    ["Up to date", upToDateKeys.length],
    [dryRun ? "To resync" : "Resynced", syncedKeys.length],
    ["Failed", failedKeys.length],
    ["Conflict, not synced", conflictKeys.length],
    ["Not a Test (ticket key used as a tag)", nonTestKeys.length],
    [notFoundLabel, unknownKeys.length],
    ["Not overwritten (Xray holds more than the steps)", protectedKeys.length],
  ];
  // One row per key that needs a look; up-to-date Tests are only counted.
  const conflictOnly = conflictKeys.filter((key) => !missingKeys.includes(key));
  const detailRows = [
    ...syncedKeys.map((key) => {
      const type = xrayTests.get(key).testType;
      return [key, dryRun ? "To resync" : "Resynced", type?.kind === "Gherkin" ? "" : `type ${type?.name} -> ${GHERKIN_TEST_TYPE}`];
    }),
    ...failedKeys.map((key) => [key, "Failed", "see the job log"]),
    ...conflictOnly.map((key) => [key, "Conflict, not synced", "same key on different scenarios"]),
    ...nonTestKeys.map((key) => [key, "Not a Test", `${nonTestTypes.get(key)} key: tag the scenario with a Test key`]),
    ...unknownKeys.map((key) => [key, notFoundLabel, classificationFailed ? "issue type lookup failed" : "deleted key, typo or non-coverable type"]),
    ...protectedKeys.map((key) => [key, "Not overwritten", "Feature, Background, Rule, tags or several scenarios in Xray"]),
  ];
  const cell = (text) => String(text).replaceAll("|", "\\|");
  const jiraLink = (key) => `[${key}](https://centreon.atlassian.net/browse/${key})`;
  const markdown = [
    ...summary,
    "",
    "| Result | Keys |",
    "| --- | ---: |",
    ...counts.map(([label, count]) => `| ${label} | ${count} |`),
  ];
  if (detailRows.length > 0) {
    markdown.push(
      "",
      "| Key | Result | Scenario | Detail |",
      "| --- | --- | --- | --- |",
      ...detailRows.map(([key, result, detail]) => `| ${jiraLink(key)} | ${result} | ${locationsOf(key).map((location) => `\`${cell(location)}\``).join("<br>")} | ${cell(detail)} |`),
    );
  }
  writeStepSummary(markdown.join("\n"));
  console.log(summary.slice(2).join("\n"));
  for (const [label, count] of counts) console.log(`- ${label}: ${count}`);

  if (failedKeys.length > 0) process.exit(1);
}

if (require.main === module) {
  main().catch((error) => {
    console.error(`ERROR: ${error.message}`);
    process.exit(1);
  });
}

module.exports = { normalizeGherkin, comparisonKey, holdsMoreThanSteps, extractTaggedScenarios, groupByKey };
