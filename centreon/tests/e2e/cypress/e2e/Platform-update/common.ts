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

const installCentreon = (version: string): Cypress.Chainable => {
  cy.execInContainer({
    command: `bash -e <<EOF
      dnf config-manager --set-disabled 'centreon-*-unstable*' 'mariadb*'
      dnf install -y centreon-web-${version}
      echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini
      service mysql start
      mkdir -p /run/php-fpm
      /usr/sbin/php-fpm
      httpd -k start
      mysql -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
EOF`,
    name: Cypress.env('dockerName')
  });

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/steps/step.php?action=nextStep'
  }).as('nextStep');

  cy.intercept({
    method: 'POST',
    url: '/centreon/install/steps/process/generationCache.php'
  }).as('cacheGeneration');

  cy.visit('/centreon/install/install.php')
    .get('th.step-wrapper span')
    .contains(1);
  cy.get('#next').click();
  cy.get('th.step-wrapper span').contains(2);
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(3);
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(4);
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(5);
  cy.get('input[name="admin_password"]').clear().type('Centreon!2021');
  cy.get('input[name="confirm_password"]').clear().type('Centreon!2021');
  cy.get('input[name="firstname"]').clear().type('centreon');
  cy.get('input[name="lastname"]').clear().type('centreon');
  cy.get('input[name="email"]').clear().type('centreon@localhost');
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(6);
  cy.get('input[name="root_password"]').clear().type('centreon');
  cy.get('input[name="db_password"]').clear().type('centreon');
  cy.get('input[name="db_password_confirm"]').clear().type('centreon');
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(7);
  cy.wait('@cacheGeneration', { timeout: 30000 })
    .get('tbody#step_contents span:contains("OK")')
    .should('have.length', 7);
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(8);
  cy.wait('@nextStep').get('#next').click();
  cy.get('th.step-wrapper span').contains(9);

  cy.wait('@nextStep').get('#finish').click();

  return cy
    .exec(
      `docker cp ../../../.github/docker/sql/standard.sql ${Cypress.env(
        'dockerName'
      )}:/tmp/standard.sql`
    )
    .execInContainer({
      command: `bash -e <<EOF
      mysql -pcentreon centreon < /tmp/standard.sql
EOF`,
      name: Cypress.env('dockerName')
    });
};

const updatePlatformPackages = (): Cypress.Chainable => {
  return cy
    .execInContainer({
      command: `mkdir /tmp/rpms-update-centreon`,
      name: Cypress.env('dockerName')
    })
    .copyOntoContainer({
      destPath: '/tmp/rpms-update-centreon',
      srcPath: './cypress/fixtures'
    })
    .getWebVersion()
    .then(({ major_version }) => {
      return cy.execInContainer({
        command: `bash -e <<EOF
        rm -f /tmp/rpms-update-centreon/centreon-${major_version}*.rpm /tmp/rpms-update-centreon/centreon-central-${major_version}*.rpm
        dnf install -y /tmp/rpms-update-centreon/*.rpm
  EOF`,
        name: Cypress.env('dockerName')
      });
    });
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
        `The platform version is not the correct one (expected: ${platformVersion}, actual: ${stdout}).`
      );
    });
};

const insertResources = (): Cypress.Chainable => {
  // return insertFixture('resources/clapi/host2/01-add.json');
  const files = [
    'resources/clapi/host1/01-add.json',
    'resources/clapi/service1/01-add.json',
    'resources/clapi/service1/02-set-max-check.json',
    'resources/clapi/service1/03-disable-active-check.json',
    'resources/clapi/service1/04-enable-passive-check.json',
    'resources/clapi/service2/01-add.json',
    'resources/clapi/service2/02-set-max-check.json',
    'resources/clapi/service2/03-disable-active-check.json',
    'resources/clapi/service2/04-enable-passive-check.json',
    'resources/clapi/service3/01-add.json',
    'resources/clapi/service3/02-set-max-check.json',
    'resources/clapi/service3/03-disable-active-check.json',
    'resources/clapi/service3/04-enable-passive-check.json'
  ];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

export {
  checkIfSystemUserRoot,
  installCentreon,
  updatePlatformPackages,
  checkPlatformVersion,
  dateBeforeLogin,
  insertResources
};
