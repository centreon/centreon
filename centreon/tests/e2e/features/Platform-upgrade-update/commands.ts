Cypress.Commands.add('getClosestVersionFile', (currentVersion, versionDir) => {
  const path = require('path');

  const pattern = /^Update-(\d+\.\d+(\.\d+)?(?:-\S+)?)\.php$/; // Updated regex to handle versions with suffixes like .beta, .rc, etc.

  // Use cy.task() to read the files in the directory
  cy.task('listFilesInDirectory', versionDir).then((files) => {
    // Filter files that match the pattern
    const versionFiles = (files || []).filter(
      (file): file is string => typeof file === "string" && pattern.test(file),
    );

    if (versionFiles.length === 0) {
      throw new Error('No version files found in the directory.');
    }

    // Extract the versions from the files
    const versions = versionFiles
      .map((file) => {
        const match = file.match(pattern);
        return match ? match[1] : null;
      })
      .filter((v): v is string => v !== null); // Ensure versions is string[]

    cy.log(`Looking for a version close to: ${currentVersion}`);

    // If the version exists, return it
    if (versions.includes(currentVersion)) {
      const versionFilePath = path.join(
        versionDir,
        `Update-${currentVersion}.php`
      );
      const version = `${currentVersion.split('.')[0]}.${currentVersion.split('.')[1]}`; // Extract the desired version
      cy.log(`Found exact version file: ${versionFilePath}`);
      return cy.wrap(version); // Only return the version, not the path
    }

    // If the version doesn't exist, find the closest version
    const closestVersionObj = versions.reduce<{
      version: string;
      diff: number;
    } | null>((closest, version) => {
      const current = currentVersion.split('.').map(Number);
      const fileVersion = version.split('.').map(Number);

      const currentDiff =
        Math.abs(current[0] - fileVersion[0]) * 100 +
        Math.abs(current[1] - fileVersion[1]);

      if (!closest || currentDiff < closest.diff) {
        return { version, diff: currentDiff };
      }

      return closest;
    }, null);

    if (closestVersionObj) {
      const versionFilePath = path.join(
        versionDir,
        `Update-${closestVersionObj.version}.php`
      );
      const closestVersionString = `${closestVersionObj.version.split('.')[0]}.${closestVersionObj.version.split('.')[1]}`; // Extract the closest version
      cy.log(`Closest version file: ${versionFilePath}`);
      return cy.wrap(closestVersionString); // Only return the closest version
    }
    throw new Error('No closest version found.');
  });
});

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      getClosestVersionFile(
        currentVersion: string,
        versionDir: string
      ): Cypress.Chainable<string>;
    }
  }
}

export {};
