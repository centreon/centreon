#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";

// ------------------------
// Input
// ------------------------
const collection = process.argv[2];
if (!collection) {
  console.error("Missing collection argument");
  console.error("Usage: node bruno-github-summary.js <collection>");
  process.exit(1);
}

// ------------------------
// Paths
// ------------------------
const mergedFile = path.join(
  process.cwd(),
  "artifacts",
  collection,
  `${collection}-merged.json`,
);

if (!fs.existsSync(mergedFile)) {
  console.error(`❌ File not found: ${mergedFile}`);

  process.exit(1);
}

const data = JSON.parse(fs.readFileSync(mergedFile, "utf-8"));

// ------------------------
// Global stats
// ------------------------
let total = 0;
let success = 0;
let failed = 0;
let totalTime = 0;

data.forEach((block) => {
  (block.results || []).forEach((t) => {
    total++;
    const status = t.response?.status;
    totalTime += t.response?.responseTime || 0;
    if (status >= 200 && status < 300) success++;
    else failed++;
  });
});

const avgTime = total ? Math.round(totalTime / total) : 0;
const globalStatus = failed === 0 ? "SUCCESS ✅" : "FAILED ❌";
const globalColor = failed === 0 ? "#16a34a" : "#dc2626";

// ------------------------
// Header (LOGO VIA URL)
// ------------------------
let html = `
<p style="margin-top:4px;">
  <strong>Collection:</strong> ${collection}
  &nbsp;|&nbsp;
  <span style="color:${globalColor}; font-weight:bold;">
    ${globalStatus}
  </span>
</p>

<hr />
`;

// ------------------------
// Stats cards
// ------------------------
html += `
<table style="width:100%; margin-bottom:20px;">
  <tr>
    <td style="background:#f1f5f9; padding:12px; border-radius:8px; text-align:center;">
      🧪<br><strong>${total}</strong><br>Total tests
    </td>
    <td style="background:#ecfdf5; padding:12px; border-radius:8px; text-align:center;">
      ✅<br><strong>${success}</strong><br>Passed
    </td>
    <td style="background:#fef2f2; padding:12px; border-radius:8px; text-align:center;">
      ❌<br><strong>${failed}</strong><br>Failed
    </td>
    <td style="background:#eff6ff; padding:12px; border-radius:8px; text-align:center;">
      ⏱️<br><strong>${avgTime} ms</strong><br>Avg time
    </td>
  </tr>
</table>
`;

// ------------------------
// Detailed table
// ------------------------
html += `
<table style="border-collapse:collapse; width:100%;">
  <tr style="background:#020617; color:white;">
    <th style="padding:8px;">📁 Test</th>
    <th style="padding:8px;">🔁 Method</th>
    <th style="padding:8px;">📡 Status</th>
    <th style="padding:8px;">⏱️ Time</th>
    <th style="padding:8px;">💬 Message</th>
  </tr>
`;

let row = 0;

data.forEach((block) => {
  (block.results || []).forEach((test) => {
    row++;
    const bg = row % 2 === 0 ? "#f8fafc" : "#ffffff";
    const status = test.response?.status ?? "-";
    const time = test.response?.responseTime ?? "-";
    const ok = status >= 200 && status < 300;
    const color = ok ? "#16a34a" : "#dc2626";

    const message = test.response?.data?.message || test.error || "";

    html += `
<tr style="background:${bg};">
  <td style="padding:8px;">${test.test?.filename}</td>
  <td style="padding:8px; text-align:center;">${test.request?.method}</td>
  <td style="padding:8px; text-align:center; color:${color}; font-weight:bold;">
    ${ok ? "✅" : "❌"} ${status}
  </td>
  <td style="padding:8px; text-align:center;">${time} ms</td>
  <td style="padding:8px; color:#dc2626;">${message}</td>
</tr>
`;
  });
});

html += `</table>`;

// ------------------------
// Output
// ------------------------
console.log(html);
