#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import { failingChecks, requestFailed } from "./bruno-result-helpers.js";

const dir = process.argv[2];
if (!dir) {
  console.error("Usage: node bruno-count-failures.js <reports-directory>");
  process.exit(1);
}

let failures = 0;

if (fs.existsSync(dir)) {
  for (const file of fs.readdirSync(dir)) {
    if (!file.endsWith("-merged.json")) continue;
    const report = JSON.parse(fs.readFileSync(path.join(dir, file), "utf-8"));
    report.forEach((iteration) => {
      (iteration.results || []).forEach((requestResult) => {
        if (requestFailed(requestResult, failingChecks(requestResult))) failures++;
      });
    });
  }
}

console.log(failures);
