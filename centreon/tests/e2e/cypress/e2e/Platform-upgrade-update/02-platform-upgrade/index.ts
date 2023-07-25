import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkPlatformVersion,
  getCentreonPreviousMajorVersion,
  getCentreonStableMinorVersions,
  installCentreon
} from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step1.php'
  }).as('getStep1');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step2.php'
  }).as('getStep2');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step3.php'
  }).as('getStep3');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step4.php'
  }).as('getStep4');

  cy.intercept({
    method: 'GET',
    url: '/centreon/install/step_upgrade/step5.php'
  }).as('getStep5');

  cy.intercept({
    method: 'POST',
    url: '/centreon/install/steps/process/generationCache.php'
  }).as('generatingCache');

  cy.intercept('/centreon/api/latest/monitoring/resources*').as(
    'monitoringEndpoint'
  );

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('generateAndReloadPollers');
});

Given(
  'a running platform in major {string} with {string} version',
  (major_version_from_expression: string, version_from_expression: string) => {
    cy.getWebVersion().then(({ major_version }) => {
      let major_version_from = '0';
      switch (major_version_from_expression) {
        case 'n - 1':
          major_version_from = getCentreonPreviousMajorVersion(major_version);
          break;
        case 'n - 2':
          major_version_from = getCentreonPreviousMajorVersion(
            getCentreonPreviousMajorVersion(major_version)
          );
          break;
        default:
          throw new Error(`${major_version_from_expression} not managed.`);
      }

      cy.startContainer({
        image: `docker.centreon.com/centreon/centreon-web-dependencies-${Cypress.env(
          'WEB_IMAGE_OS'
        )}:${major_version_from}`,
        name: Cypress.env('dockerName'),
        portBindings: [
          {
            destination: 4000,
            source: 80
          }
        ]
      }).then(() => {
        Cypress.config('baseUrl', 'http://0.0.0.0:4000');

        return cy
          .intercept('/waiting-page', {
            headers: { 'content-type': 'text/html' },
            statusCode: 200
          })
          .visit('/waiting-page')
          .then(() => {
            return getCentreonStableMinorVersions(major_version_from).then(
              (stable_minor_versions) => {
                let minor_version_index = 0;
                switch (version_from_expression) {
                  case 'last stable':
                    minor_version_index = stable_minor_versions.length - 1;
                    break;
                  case 'last stable - 1':
                    minor_version_index = stable_minor_versions.length - 2;
                    break;
                  default:
                    throw new Error(`${version_from_expression} not managed.`);
                }
                if (minor_version_index <= 0) {
                  cy.log(
                    `Not needed to test ${version_from_expression} version.`
                  );

                  return Cypress.runner.stop();
                }

                cy.log(
                  `${version_from_expression} version is ${minor_version_index}`
                );

                return installCentreon(
                  `${major_version_from}.${stable_minor_versions[minor_version_index]}`
                )
                  .then(() => {
                    if (Cypress.env('WEB_IMAGE_OS').includes('alma')) {
                      const distrib =
                        Cypress.env('WEB_IMAGE_OS') === 'alma9' ? 'el9' : 'el8';

                      return cy.execInContainer({
                        command: `bash -e <<EOF
                          dnf config-manager --add-repo https://packages.centreon.com/rpm-standard/${major_version}/${distrib}/centreon-${major_version}.repo
                          dnf config-manager --set-enabled 'centreon*'
EOF`,
                        name: Cypress.env('dockerName')
                      });
                    }

                    return cy.execInContainer({
                      command: `bash -e <<EOF
                        echo "deb https://packages.centreon.com/apt-standard-${major_version}-stable/ bullseye main" > /etc/apt/sources.list.d/centreon-stable.list
                        echo "deb https://packages.centreon.com/apt-standard-${major_version}-testing/ bullseye main" > /etc/apt/sources.list.d/centreon-testing.list
                        echo "deb https://packages.centreon.com/apt-standard-${major_version}-unstable/ bullseye main" > /etc/apt/sources.list.d/centreon-unstable.list
                        echo "deb https://packages.centreon.com/apt-plugins-stable/ bullseye main" > /etc/apt/sources.list.d/centreon-plugins-stable.list
                        echo "deb https://packages.centreon.com/apt-plugins-testing/ bullseye main" > /etc/apt/sources.list.d/centreon-plugins-testing.list
                        echo "deb https://packages.centreon.com/apt-plugins-unstable/ bullseye main" > /etc/apt/sources.list.d/centreon-plugins-unstable.list
                        wget -O- https://packages.centreon.com/api/security/keypair/Debian/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1
                        apt-get update
EOF`,
                      name: Cypress.env('dockerName')
                    });
                  })
                  .then(() => {
                    return checkPlatformVersion(
                      `${major_version_from}.${stable_minor_versions[minor_version_index]}`
                    ).then(() => cy.visit('/'));
                  });
              }
            );
          });
      });
    });
  }
);

afterEach(() => {
  cy.stopWebContainer();
});
