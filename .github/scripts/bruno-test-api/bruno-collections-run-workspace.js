#!/usr/bin/env node

import { execSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

// Resolve `segments` under `root` and refuse any path that escapes it.
// Defense-in-depth against path traversal: validates the resolved location,
// so a malicious segment (e.g. "../../etc") is rejected instead of read.
function resolveWithin(root, ...segments) {
  const resolvedRoot = path.resolve(root);
  const resolved = path.resolve(resolvedRoot, ...segments);
  if (resolved !== resolvedRoot && !resolved.startsWith(resolvedRoot + path.sep)) {
    console.error(`Refusing path outside of "${resolvedRoot}": ${resolved}`);
    process.exit(1);
  }
  return resolved;
}

// ----------------------
// Arguments
// ----------------------
const collection = process.argv[2];
if (!collection) {
  console.error("Usage: node bruno-collections-run.js <collection-name>");
  process.exit(1);
}
if (!/^[A-Za-z0-9_-]+$/.test(collection)) {
  console.error(
    `Invalid collection name: "${collection}" (expected ^[A-Za-z0-9_-]+$)`,
  );
  process.exit(1);
}
console.log("Collection name:", collection);

// ----------------------
// Paths
// ----------------------
const collectionPath = resolveWithin(
  path.join(process.cwd(), "collections"),
  collection,
);
if (!fs.existsSync(collectionPath)) {
  console.error(`❌ Collection directory not found: ${collectionPath}`);
  process.exit(1);
}

// ----------------------
// Environment files
// ----------------------
const globalEnvironmentFileWithoutExtension = getEnvironmentFile(
  process.cwd(),
  ".yml",
);
const environmentFileWithoutExtension = getEnvironmentFile(collectionPath);

// Helper function
function getEnvironmentFile(collectionPath, extension) {
  const envDir = resolveWithin(collectionPath, "environments");
  if (!fs.existsSync(envDir)) return false;
  const ext = extension ?? ".bru";
  const envFiles = fs.readdirSync(envDir).filter((f) => f.endsWith(ext));
  if (!envFiles.length) return false;
  return envFiles[0].slice(0, -ext.length);
}

// ----------------------
// Main execution
// Run the entire collection in a single bru run so that bru.setVar() values
// (e.g. dashboardId created in folder 01) persist across folders in the same process.
// Running per-subfolder would lose all runtime variables between invocations.
// ----------------------
const artifactsDir = path.join(process.cwd(), "artifacts", collection);
if (!fs.existsSync(artifactsDir))
  fs.mkdirSync(artifactsDir, { recursive: true });

const tagsExcluded = "ignore";

const jsonReport = path.join(artifactsDir, `${collection}-merged.json`);
const htmlReport = path.join(artifactsDir, `${collection}.html`);
// JUnit report consumed by the Xray import. Stable per-collection name so the
// import workflow can match every collection's file with a single `*-junit.xml`
// find pattern (mirrors jsonReport/htmlReport naming above).
const junitReport = path.join(artifactsDir, `${collection}-junit.xml`);

const argEnv = environmentFileWithoutExtension
  ? `--env "${environmentFileWithoutExtension}"`
  : "";

const globalArgEnv = globalEnvironmentFileWithoutExtension
  ? `--global-env "${globalEnvironmentFileWithoutExtension}"`
  : "";

const cmd = [
  "npx bru run",
  `--workspace-path ./../..`,
  globalArgEnv,
  argEnv,
  `--exclude-tags ${tagsExcluded}`,
  `--reporter-json "${jsonReport}"`,
  `--reporter-html "${htmlReport}"`,
  `--reporter-junit "${junitReport}"`,
].join(" ");

console.log("\n============================================================");
console.log(`⚪️ START COLLECTION: ${collection}`);
console.log("Executing:", cmd);

let failureCount = 0;
let successCount = 0;

try {
  execSync(cmd, { cwd: collectionPath, stdio: "inherit" });
  console.log(`\n🟢 SUCCESS: ${collection}`);
  successCount = 1;
} catch {
  console.error(`\n🔴 FAILED: ${collection}`);
  failureCount = 1;
}

console.log(`\n📄 Report generated: ${jsonReport}`);

// ----------------------
// Update GitHub Summary using bruno-github-summary.js
// ----------------------
const githubSummaryScript = path.join(
  process.cwd(),
  ".github/scripts/bruno-test-api/bruno-github-summary.js",
);
if (fs.existsSync(githubSummaryScript) && process.env.GITHUB_STEP_SUMMARY) {
  execSync(
    `node "${githubSummaryScript}" "${collection}" >> "${process.env.GITHUB_STEP_SUMMARY}"`,
    { stdio: "inherit" },
  );
  console.log("✅ GitHub Summary updated");
}

// ----------------------
// Final console summary
// ----------------------
console.log("\n************ SUMMARY *****************");
console.log(`🟢 Successful: ${successCount}`);
console.log(`🔴 Failed:     ${failureCount}`);
console.log("*****************************************\n");

process.exit(failureCount > 0 ? 1 : 0);
