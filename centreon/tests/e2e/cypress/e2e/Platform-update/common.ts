const nonDefaultPassword = 'Password123!';

const setUserAdminDefaultCredentials = (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: `admin;password;${nonDefaultPassword}`
    }
  });
};

const setDatabaseUserRootDefaultCredentials = (): Cypress.Chainable => {
  const query = `ALTER USER 'root'@'localhost' IDENTIFIED BY '${nonDefaultPassword}';`;
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon -e "${query}"`;

  return cy.exec(command);
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
  setDatabaseUserRootDefaultCredentials,
  checkIfSystemUserRoot,
  updatePlatformPackages,
  checkPlatformVersion,
  injectingModulesLicense
};
