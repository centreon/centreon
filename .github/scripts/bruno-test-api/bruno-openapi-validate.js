#!/usr/bin/env node

import fs from "node:fs";
import { createRequire } from "node:module";
import path from "node:path";

// Ajv is a dependency of tests/api, resolved from the working directory and not from this script.
const require = createRequire(path.join(process.cwd(), "package.json"));
const Ajv2020 = require("ajv/dist/2020").default;
const addFormats = require("ajv-formats");

// ------------------------
// Input
// ------------------------
const collection = process.argv[2];
if (!collection) {
  console.error("Usage: node bruno-openapi-validate.js <collection>");
  process.exit(1);
}
if (!/^[A-Za-z0-9_-]+$/.test(collection)) {
  console.error(
    `Invalid collection name: "${collection}" (expected ^[A-Za-z0-9_-]+$)`,
  );
  process.exit(1);
}

const specFile = process.env.OPENAPI_SPEC;
if (!specFile || !fs.existsSync(specFile)) {
  console.error(`❌ Bundled OpenAPI spec not found: ${specFile}`);
  process.exit(1);
}
const strict = process.env.OPENAPI_VALIDATION_MODE === "strict";

const artifactsDir = path.join(process.cwd(), "artifacts", collection);
const reportFile = path.join(artifactsDir, `${collection}-merged.json`);
if (!fs.existsSync(reportFile)) {
  console.error(`❌ File not found: ${reportFile}`);
  process.exit(1);
}

const spec = JSON.parse(fs.readFileSync(specFile, "utf-8"));
const report = JSON.parse(fs.readFileSync(reportFile, "utf-8"));

// ------------------------
// Schema compilation
// ------------------------
const ajv = new Ajv2020({
  strict: false,
  allErrors: true,
  validateSchema: false,
  logger: false,
});
// Fast mode accepts dotless email domains such as admin@localhost, used by default Centreon contacts.
addFormats(ajv, { mode: "fast" });
ajv.addSchema(spec, "openapi");

const validators = new Map();
function getValidator(pointer) {
  if (!validators.has(pointer)) {
    validators.set(pointer, ajv.compile({ $ref: `openapi#${pointer}` }));
  }
  return validators.get(pointer);
}

function toPointer(...segments) {
  return segments
    .map(
      (s) =>
        "/" +
        encodeURIComponent(String(s).replace(/~/g, "~0").replace(/\//g, "~1")),
    )
    .join("");
}

// Follows OpenAPI Reference Objects, returning the target node and its pointer.
function resolve(node, pointer) {
  const seen = new Set();
  while (node?.$ref?.startsWith("#/") && !seen.has(node.$ref)) {
    seen.add(node.$ref);
    pointer = node.$ref
      .slice(1)
      .split("/")
      .map((s) => encodeURIComponent(decodeURIComponent(s)))
      .join("/");
    node = node.$ref
      .slice(2)
      .split("/")
      .map((s) => decodeURIComponent(s).replace(/~1/g, "/").replace(/~0/g, "~"))
      .reduce((acc, key) => acc?.[key], spec);
  }
  return { node, pointer };
}

// ------------------------
// Operation lookup
// ------------------------
const basePaths = (spec.servers ?? [{ url: "/" }]).map((server) => {
  const url = server.url.replace(
    /\{([^}]+)\}/g,
    (_, name) => server.variables?.[name]?.default ?? name,
  );
  return new URL(url, "http://localhost").pathname.replace(/\/$/, "");
});

const operations = Object.entries(spec.paths ?? {}).map(([template, item]) => {
  const segments = template.split("/").filter(Boolean);
  return {
    template,
    item,
    literals: segments.filter((s) => !s.startsWith("{")).length,
    regex: new RegExp(
      "^/" +
        segments
          .map((s) =>
            s.startsWith("{")
              ? "[^/]+"
              : s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"),
          )
          .join("/") +
        "$",
    ),
  };
});

function findOperation(method, rawUrl) {
  let pathname;
  try {
    pathname = decodeURI(new URL(rawUrl).pathname).replace(/\/+$/, "");
  } catch {
    return null;
  }
  for (const base of basePaths) {
    if (base && !pathname.startsWith(base + "/")) continue;
    const relative = pathname.slice(base.length);
    // API Platform documents version-less paths (/api/...) that are also served under /api/{version}/...
    const candidates = [
      relative,
      relative.replace(/^\/api\/(latest|beta|v\d+(\.\d+)*)\//, "/api/"),
    ];
    const match = candidates
      .map((candidate) =>
        operations
          .filter((op) => op.item[method] && op.regex.test(candidate))
          .sort((a, b) => b.literals - a.literals)[0],
      )
      .find(Boolean);
    if (match) {
      return {
        template: match.template,
        operation: match.item[method],
        pointer: toPointer("paths", match.template, method),
      };
    }
  }
  return null;
}

// ------------------------
// Helpers
// ------------------------
function header(headers, name) {
  const key = Object.keys(headers ?? {}).find(
    (k) => k.toLowerCase() === name.toLowerCase(),
  );
  return key ? String(headers[key]) : "";
}

function mediaType(headers) {
  return header(headers, "content-type").split(";")[0].trim().toLowerCase();
}

function isJson(type) {
  return type === "application/json" || type.endsWith("+json");
}

function parseBody(data) {
  if (typeof data !== "string") return data;
  if (data.trim() === "") return undefined;
  try {
    return JSON.parse(data);
  } catch {
    return data;
  }
}

function isEmpty(body) {
  return body === undefined || body === null || body === "";
}

function formatErrors(errors) {
  return (errors ?? [])
    .slice(0, 5)
    .map((e) => `${e.instancePath || "(root)"} ${e.message}`)
    .join("; ");
}

// ------------------------
// Validation
// ------------------------
const violations = new Map();
let validated = 0;
let undocumented = 0;
const outsideSpec = new Map();
let expectedRejections = 0;

function addViolation(result, endpoint, status, location, message) {
  const key = [endpoint, status, location, message].join("|");
  const existing = violations.get(key);
  if (existing) {
    existing.count++;
    return;
  }
  violations.set(key, {
    endpoint,
    status,
    location,
    message,
    test: result.test?.filename ?? "-",
    count: 1,
  });
}

// Returns the validator of a Media Type Object's schema, or null when it has none.
function mediaTypeValidator(content, contentPointer, type) {
  if (!content?.[type]) return undefined;
  const media = resolve(content[type], contentPointer + toPointer(type));
  if (!media.node?.schema) return null;
  return getValidator(media.pointer + toPointer("schema"));
}

function validateRequest(result, found, endpoint, status) {
  const body = parseBody(result.request.data);
  const type = mediaType(result.request.headers);
  if (!found.operation.requestBody || isEmpty(body) || !isJson(type)) return;

  const requestBody = resolve(
    found.operation.requestBody,
    found.pointer + toPointer("requestBody"),
  );
  const validate = mediaTypeValidator(
    requestBody.node?.content,
    requestBody.pointer + toPointer("content"),
    type,
  );
  // A request the spec rejects is expected to fail: only flag it when the server accepted it.
  const accepted = status >= 200 && status < 300;
  if (validate === undefined) {
    if (accepted) {
      addViolation(
        result,
        endpoint,
        status,
        "request",
        `undocumented content type ${type}`,
      );
    }
    return;
  }
  if (!validate || validate(body)) return;
  if (accepted) {
    addViolation(
      result,
      endpoint,
      status,
      "request",
      formatErrors(validate.errors),
    );
  } else {
    expectedRejections++;
  }
}

function validateResponse(result, found, endpoint, status) {
  const responses = found.operation.responses ?? {};
  const statusKey = [String(status), `${String(status)[0]}XX`, "default"].find(
    (k) => responses[k],
  );
  if (!statusKey) {
    addViolation(
      result,
      endpoint,
      status,
      "response",
      "undocumented status code",
    );
    return;
  }

  const body = parseBody(result.response.data);
  const type = mediaType(result.response.headers);
  if (isEmpty(body) || !isJson(type)) return;

  const response = resolve(
    responses[statusKey],
    found.pointer + toPointer("responses", statusKey),
  );
  const validate = mediaTypeValidator(
    response.node?.content,
    response.pointer + toPointer("content"),
    type,
  );
  if (validate === undefined) {
    addViolation(
      result,
      endpoint,
      status,
      "response",
      `undocumented content type ${type}`,
    );
  } else if (validate && !validate(body)) {
    addViolation(
      result,
      endpoint,
      status,
      "response",
      formatErrors(validate.errors),
    );
  }
}

report.forEach((iteration) => {
  (iteration.results || []).forEach((result) => {
    const method = result.request?.method?.toLowerCase();
    const status = result.response?.status;
    if (!method || !result.request?.url || !status) return;

    const found = findOperation(method, result.request.url);
    if (!found) {
      undocumented++;
      let pathname = result.request.url;
      try {
        pathname = new URL(pathname).pathname.replace(
          /\/\d+(?=\/|$)/g,
          "/{id}",
        );
      } catch {}
      const key = `${method.toUpperCase()} ${pathname}`;
      outsideSpec.set(key, (outsideSpec.get(key) ?? 0) + 1);
      return;
    }
    validated++;
    const endpoint = `${method.toUpperCase()} ${found.template}`;
    validateRequest(result, found, endpoint, status);
    validateResponse(result, found, endpoint, status);
  });
});

// ------------------------
// Output
// ------------------------
const list = [...violations.values()];
fs.writeFileSync(
  path.join(artifactsDir, `${collection}-openapi-validation.json`),
  JSON.stringify(
    {
      validated,
      undocumented,
      expectedRejections,
      violations: list,
      outsideSpec: Object.fromEntries(outsideSpec),
    },
    null,
    2,
  ),
);

const level = strict ? "error" : "warning";
list.slice(0, 20).forEach((v) => {
  const message = `${v.endpoint} → ${v.status} (${v.location}): ${v.message}`;
  console.log(
    `::${level} title=OpenAPI contract (${collection})::${message.replace(/\r?\n/g, " ")}`,
  );
});

console.log(
  `OpenAPI validation for ${collection}: ${validated} requests checked, ` +
    `${list.length} distinct violations, ${undocumented} requests outside the spec, ` +
    `${expectedRejections} invalid requests rejected as expected.`,
);

if (process.env.GITHUB_STEP_SUMMARY) {
  const escape = (s) => String(s).replace(/\|/g, "\\|").replace(/\r?\n/g, " ");
  let md = `\n### OpenAPI contract validation\n\n`;
  md += `${validated} requests checked against the spec, ${undocumented} outside of it, `;
  md += `${list.length} distinct violations.\n\n`;
  if (list.length) {
    md += `| Endpoint | Status | Location | Violation | Occurrences | First test |\n`;
    md += `| --- | --- | --- | --- | --- | --- |\n`;
    list.forEach((v) => {
      md += `| ${escape(v.endpoint)} | ${v.status} | ${v.location} | ${escape(v.message)} | ${v.count} | ${escape(v.test)} |\n`;
    });
  }
  if (outsideSpec.size) {
    md += `\n<details><summary>Requests outside the spec</summary>\n\n`;
    outsideSpec.forEach((count, key) => {
      md += `- \`${key}\` (${count})\n`;
    });
    md += `\n</details>\n`;
  }
  fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, md);
}

process.exit(strict && list.length > 0 ? 1 : 0);
