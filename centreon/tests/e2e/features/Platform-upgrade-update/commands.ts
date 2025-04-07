Cypress.Commands.add("getClosestVersionFile", (currentVersion) => {
  const fs = require("fs");
  const path = require("path");

  const versionDir = "./././../../www/install/php"; // Le dossier où les fichiers sont stockés
  const pattern = /^Update-(\d+\.\d+(\.\d+)?(?:-\S+)?)\.php$/; // Mise à jour du regex pour gérer les versions avec suffixes comme .beta, .rc, etc.

  // Utilisation de cy.task() pour lire les fichiers dans le répertoire
  cy.task("listFilesInDirectory", versionDir).then((files) => {
    // Filtrer les fichiers qui correspondent au pattern
    const versionFiles = files.filter((file) => pattern.test(file));

    if (versionFiles.length === 0) {
      throw new Error("No version files found in the directory.");
    }

    // Extraire les versions des fichiers
    const versions = versionFiles
      .map((file) => {
        const match = file.match(pattern);
        return match ? match[1] : null;
      })
      .filter(Boolean); // Nettoyer les nulls

    // Exécuter getCentreonPreviousMajorVersion pour obtenir la version demandée
    const desiredVersion = currentVersion;
    cy.log(`Looking for version: ${desiredVersion}`);

    // Si la version existe, la retourner
    if (versions.includes(desiredVersion)) {
      const versionFilePath = path.join(
        versionDir,
        `Update-${desiredVersion}.php`,
      );
      const version =
        desiredVersion.split(".")[0] + "." + desiredVersion.split(".")[1]; // Extraction de la version souhaitée
      cy.log(`Found exact version file: ${versionFilePath}`);
      return cy.wrap(version); // Retourne uniquement la version, pas le chemin
    }

    // Si la version n'existe pas, chercher la version la plus proche
    const closestVersion = versions.reduce((closest, version) => {
      const current = desiredVersion.split(".").map(Number);
      const fileVersion = version.split(".").map(Number);

      const currentDiff =
        Math.abs(current[0] - fileVersion[0]) * 100 +
        Math.abs(current[1] - fileVersion[1]);

      if (!closest || currentDiff < closest.diff) {
        return { version, diff: currentDiff };
      }

      return closest;
    }, null);

    if (closestVersion) {
      const versionFilePath = path.join(
        versionDir,
        `Update-${closestVersion.version}.php`,
      );
      const closestVersionString =
        closestVersion.version.split(".")[0] +
        "." +
        closestVersion.version.split(".")[1]; // Extraction de la version la plus proche
      cy.log(`Closest version file: ${versionFilePath}`);
      return cy.wrap(closestVersionString); // Retourne uniquement la version la plus proche
    } else {
      throw new Error("No closest version found.");
    }
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      getClosestVersionFile(currentVersion: string): Cypress.Chainable<string>;
    }
  }
}

export {};
