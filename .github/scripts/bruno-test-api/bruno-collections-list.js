#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";

console.log("PWD :", process.cwd());

const COLLECTIONS_DIR = process.env.COLLECTIONS_DIR;

if (!COLLECTIONS_DIR) {
  console.error("Error: COLLECTIONS_DIR is not defined");

  process.exit(1);
}

if (!fs.existsSync(COLLECTIONS_DIR)) {
  console.error("Error: COLLECTIONS_DIR does not exist:", COLLECTIONS_DIR);

  process.exit(1);
}

function isCollectionIgnored(collectionDir) {
  const collectionBru = path.join(collectionDir, "collection.bru");
  if (!fs.existsSync(collectionBru)) return false;
  const content = fs.readFileSync(collectionBru, "utf-8");
  const metaMatch = content.match(/meta\s*\{([^}]*)\}/s);
  if (!metaMatch) return false;
  return /^\s*tags\s*:.*\bignore\b/m.test(metaMatch[1]);
}

const allCollections = fs
  .readdirSync(COLLECTIONS_DIR)
  .filter((name) =>
    fs.statSync(path.join(COLLECTIONS_DIR, name)).isDirectory(),
  );

const ignoredCollections = allCollections.filter((name) => {
  const base = path.resolve(COLLECTIONS_DIR);
  const target = path.resolve(base, name);
  const relative = path.relative(base, target);
  if (
    relative === ".." ||
    relative.startsWith(`..${path.sep}`) ||
    path.isAbsolute(relative)
  ) {
    return false;
  }
  return isCollectionIgnored(target);
});

const collections = allCollections.filter(
  (name) => !ignoredCollections.includes(name),
);

if (ignoredCollections.length) {
  console.log("Collections ignored:", ignoredCollections.join(", "));
}
console.log("Collections found:", collections.join(", "));

// Output JSON compatible matrix : ["map-ng","map-tests"]
fs.writeFileSync("collections.json", JSON.stringify(collections));

console.log("Generated collections.json");
