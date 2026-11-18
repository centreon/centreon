// Merge several JUnit XML reports (one per Bruno collection) into a single
// <testsuites> document, so the Xray import creates a single Test Execution.
//
// While merging, normalize each <testcase name="..."> so the resulting Xray Test
// summary is identifiable and collision-free. Bruno names a testcase after its
// assertion (e.g. "res.status eq 200"), which collides across every request that
// asserts the same status. We re-inject the request context already present in the
// parent <testsuite> (package = folder, name = request) to build, for example:
//   [API] By Admin > Login with admin user - res.status eq 200
const fs = require("node:fs");

const [outputFile, ...inputFiles] = process.argv.slice(2);

if (!outputFile || inputFiles.length === 0) {
  console.error("Usage: node merge-junit.js <output.xml> <input1.xml> [input2.xml ...]");
  process.exit(1);
}

// Read one attribute value from an opening tag, keeping its original XML escaping.
function readAttribute(openingTag, attributeName) {
  const attributeMatch = openingTag.match(new RegExp(`\\b${attributeName}="([^"]*)"`));
  return attributeMatch ? attributeMatch[1] : "";
}

// Build the normalized testcase name from the parent suite's context.
// Inputs are already XML-escaped (they come straight from attributes), and the
// separators are plain Unicode, so the result stays a valid attribute value.
function buildNormalizedName(folderName, requestName, assertionName) {
  const requestLocation = folderName ? `${folderName} > ${requestName}` : requestName;
  // "Tests" is Bruno's placeholder when a request has no named assertion: drop it.
  const assertionSuffix = assertionName && assertionName !== "Tests" ? ` - ${assertionName}` : "";
  return `[API] ${requestLocation}${assertionSuffix}`;
}

// Rewrite every <testcase>'s name attribute inside a single <testsuite> block.
function normalizeSuiteTestcaseNames(suiteBlock) {
  const suiteOpeningTag = suiteBlock.match(/<testsuite\b[^>]*>/)[0];
  const folderName = readAttribute(suiteOpeningTag, "package");
  const requestName = readAttribute(suiteOpeningTag, "name");

  return suiteBlock.replace(/<testcase\b[^>]*?\/?>/g, (testcaseTag) => {
    const assertionName = readAttribute(testcaseTag, "name");
    const normalizedTestcaseName = buildNormalizedName(folderName, requestName, assertionName);
    return testcaseTag.includes('name="')
      ? testcaseTag.replace(/\bname="[^"]*"/, `name="${normalizedTestcaseName}"`)
      : testcaseTag.replace(/<testcase\b/, `<testcase name="${normalizedTestcaseName}"`);
  });
}

const normalizedSuites = [];
for (const inputFile of inputFiles) {
  const junitXml = fs.readFileSync(inputFile, "utf-8");
  // Match either a self-closing <testsuite .../> or a <testsuite ...>...</testsuite>
  // block. The two branches are kept separate so the non-greedy inner match is not
  // cut short by a self-closing child such as <testcase .../>.
  const suiteBlocks = junitXml.match(/<testsuite\b(?:[^>]*\/>|[^>]*>[\s\S]*?<\/testsuite>)/g);
  if (suiteBlocks) {
    normalizedSuites.push(...suiteBlocks.map(normalizeSuiteTestcaseNames));
  }
}

if (normalizedSuites.length === 0) {
  console.error("ERROR: no <testsuite> elements found in the provided JUnit files");
  process.exit(1);
}

const mergedXml = `<?xml version="1.0" encoding="UTF-8"?>\n<testsuites>\n${normalizedSuites.join("\n")}\n</testsuites>\n`;
fs.writeFileSync(outputFile, mergedXml);
console.log(`Merged ${inputFiles.length} JUnit file(s), ${normalizedSuites.length} testsuite(s) -> ${outputFile}`);
