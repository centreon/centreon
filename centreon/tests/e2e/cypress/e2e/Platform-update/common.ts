import { executeActionViaClapi } from '../../commons';

const nonDefaultPassword = 'Password123!';

const setUserAdminDefaultCredentials = (): Cypress.Chainable => {
  return executeActionViaClapi({
    action: 'setParam',
    object: 'CONTACT',
    values: `admin;password;${nonDefaultPassword}`
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

      cy.exec(
        `docker exec -i ${Cypress.env(
          'dockerName'
        )} chmod 777 -R /var/cache/centreon/symfony/`
      );
    });
};

export {
  setUserAdminDefaultCredentials,
  setDatabaseUserRootDefaultCredentials,
  checkIfSystemUserRoot,
  updatePlatformPackages
};
