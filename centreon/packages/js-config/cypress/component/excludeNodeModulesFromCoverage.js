const fs = require('fs');
const path = require('path');

const filePath = process.argv[2];

const { error: logError } = console;

try {
  const outFile = fs.readFileSync(path.resolve(filePath)).toString();
  const outFileJson = JSON.parse(outFile);

  const coveragesWithoutNodeModules = Object.entries(outFileJson)
    .map(([key, value]) => {
      if (key.includes('node_modules')) {
        return undefined;
      }

      return [key, value];
    })
    .filter((v) => v);

  const finalOutJson = coveragesWithoutNodeModules.reduce(
    (acc, [key, value]) => ({
      ...acc,
      [key]: value
    }),
    {}
  );

  fs.writeFileSync(
    path.resolve(filePath),
    JSON.stringify(finalOutJson, null, 2)
  );
} catch (error) {
  logError(error.message);
}
