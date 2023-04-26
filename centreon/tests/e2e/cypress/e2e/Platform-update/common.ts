import { insertFixture } from '../../commons';

const dateBeforeLogin = new Date();

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

const installCentreonPackages = (version: string): Cypress.Chainable => {
  return cy.execInContainer({
    command: `bash -c "dnf config-manager --set-disabled 'centreon-*-unstable*' && dnf install -y centreon-web-${version}"`,
    name: Cypress.env('dockerName')
  });
};

const updatePlatformPackages = (): Cypress.Chainable => {
  return cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} sh -c "dnf clean all --enablerepo=* && dnf --nogpgcheck -y update centreon\\*"`
  );
};

const checkPlatformVersion = (platformVersion: string): Cypress.Chainable => {
  return cy
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} sh -c "rpm -qa |grep centreon-web |cut -d '-' -f3"`
    )
    .then(({ stdout }): Cypress.Chainable<null> | null => {
      const isRoot = platformVersion === stdout;
      if (isRoot) {
        return null;
      }

      throw new Error(
        `The platform version isn't the correct one (expected: ${platformVersion}, actual: ${stdout}).`
      );
    });
};

const insertHost = (): Cypress.Chainable => {
  return insertFixture('resources/clapi/host2/01-add.json');
};

export {
  checkIfSystemUserRoot,
  installCentreonPackages,
  updatePlatformPackages,
  checkPlatformVersion,
  dateBeforeLogin,
  insertHost
};
