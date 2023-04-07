const setUserAdminDefaultCredentials = (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: `admin;password;Password123\\!`
      //values: `admin;password;${nonDefaultPassword}`
    }
  });
};

const checkIfSystemUserRoot = (): Cypress.Chainable => {
  return cy
    .exec(`docker exec -i ${Cypress.env('dockerName')} whoami`)
    .then(({ stdout }): Cypress.Chainable<null> | null => {
      const isRoot = stdout === 'root';
      if (isRoot) {
        return null;
      }

      throw new Error(`System user is not root.`);
    });
};

const updatePlatformPackages = (): Cypress.Chainable => {
  return cy
    .exec(
      `docker cp cypress/scripts/platform-update-commands.sh ${Cypress.env(
        'dockerName'
      )}:/tmp/platform-update-commands.sh`
    )
    .then(() => {
      cy.exec(
        `docker exec -i ${Cypress.env(
          'dockerName'
        )} bash /tmp/platform-update-commands.sh`
      );
    });
};

const checkPlatformVersion = (platformVersion: string): Cypress.Chainable => {
  return cy
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} rpm -qa |grep centreon-web |cut -d '-' -f3`
    )
    .then(({ stdout }): Cypress.Chainable<null> | null => {
      const isRoot = platformVersion === stdout;
      if (isRoot) {
        return null;
      }

      throw new Error(`The platform version isn't the correct one.`);
    });
};

const injectingModulesLicense = (): Cypress.Chainable => {
  return cy.exec(
    `docker cp cypress/scripts/license/epp.license ${Cypress.env(
      'dockerName'
    )}:/etc/centreon/license.d/epp.license`
  );
};

export {
  setUserAdminDefaultCredentials,
  checkIfSystemUserRoot,
  updatePlatformPackages,
  checkPlatformVersion,
  injectingModulesLicense
};
