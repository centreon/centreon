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

const installEnginStatusWidget = (): Cypress.Chainable => {
  return cy
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} sh -c "dnf -y install centreon-widget-engine-status"`
    )
    .then(({ stdout }): Cypress.Chainable<null> | null => {
      if (stdout) {
        return null;
      }

      throw new Error(`Cannot download engine status widget.`);
    });
};

export {
  setUserAdminDefaultCredentials,
  setDatabaseUserRootDefaultCredentials,
  checkIfSystemUserRoot,
  installEnginStatusWidget
};
