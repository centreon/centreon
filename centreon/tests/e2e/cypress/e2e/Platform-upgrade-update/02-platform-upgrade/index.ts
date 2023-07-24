import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkIfConfigurationIsExported } from '../../../commons';
import {
  checkPlatformVersion,
  dateBeforeLogin,
  getCentreonPreviousMajorVersion,
  getCentreonStableMinorVersions,
  installCentreon,
  updatePlatformPackages
} from '../common';

beforeEach(() => {
  cy.getWebVersion().then(({ major_version, minor_version }) => {
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

    return cy
      .startContainer({
        image: `docker.centreon.com/centreon/centreon-web-dependencies-${Cypress.env(
          'WEB_IMAGE_OS'
        )}:${major_version}`,
        name: Cypress.env('dockerName'),
        portBindings: [
          {
            destination: 4000,
            source: 80
          }
        ]
      })
      .then(() => {
        Cypress.config('baseUrl', 'http://0.0.0.0:4000');

        return cy
          .intercept('/waiting-page', {
            headers: { 'content-type': 'text/html' },
            statusCode: 200
          })
          .visit('/waiting-page');
      });
  });
});

Given(
  'a running platform in major {string} with {string} version',
  (major_version_from_expression: string, version_from_expression: string) => {
    cy.getWebVersion().then(({ major_version, minor_version }) => {
      let major_version_from = '0';
      switch (version_from_expression) {
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
        )}:${major_version}`,
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
                if (stable_minor_versions.length === 0) {
                  cy.log(`centreon web is currently not available as stable`);

                  return Cypress.runner.stop();
                }
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
                  `${major_version}.${stable_minor_versions[minor_version_index]}`
                ).then(() => {
                  return checkPlatformVersion(
                    `${major_version}.${stable_minor_versions[minor_version_index]}`
                  ).then(() => cy.visit('/'));
                });
              }
            );
          });
      });
    });
  }
);

When('administrator updates packages to current version', () => {
  updatePlatformPackages();
});

When('administrator runs the update procedure', () => {
  cy.visit('/');

  cy.wait('@getStep1').then(() => {
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep2').then(() => {
    cy.get('span[style]').each(($span) => {
      cy.wrap($span).should('have.text', 'Loaded');
    });
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep3').get('.btc.bt_info').eq(0).click();

  cy.wait('@generatingCache')
    .get('span[style]', { timeout: 15000 })
    .each(($span) => {
      cy.wrap($span).should('have.text', 'OK');
    });
  cy.get('.btc.bt_info', { timeout: 15000 }).eq(0).click();

  cy.wait('@getStep5').get('.btc.bt_success').eq(0).click();
});

Then(
  'monitoring should be up and running after update procedure is complete to current version',
  () => {
    cy.setUserTokenApiV1();

    cy.addTimePeriod({
      name: '24/7'
    })
      .addCheckCommand({
        command: 'echo "failure" && exit 2',
        enableShell: true,
        name: 'check_command'
      })
      .addHost({
        checkCommand: 'check_command',
        name: 'host1'
      })
      .addServiceTemplate({
        name: 'serviceTemplate1'
      })
      .addService({
        checkCommand: 'check_command',
        host: 'host1',
        name: 'service1',
        template: 'serviceTemplate1'
      })
      .applyPollerConfiguration();

    cy.loginByTypeOfUser({
      jsonName: 'admin'
    }).wait('@getLastestUserFilters');

    cy.url().should('include', '/monitoring/resources');

    cy.get('[aria-label="State filter"]').click();
    cy.get('[data-value="all"]').click();

    cy.waitUntil(
      () => {
        cy.get('[aria-label="Refresh"]').click({ force: true });

        return cy.get('#content').then(($el) => {
          return $el.find(':contains("service1")').length > 0;
        });
      },
      {
        timeout: 20000
      }
    );
  }
);

Then('legacy services grid page should still work', () => {
  cy.visit('/centreon/main.php?p=20204&o=svcOV_pb').wait('@getTimeZone');

  cy.waitUntil(() => {
    cy.get('iframe#main-content')
      .its('0.contentDocument.body')
      .find('select#typeDisplay2')
      .select('All');

    cy.getIframeBody().find('a#JS_monitoring_refresh').click({ force: true });

    return cy
      .getIframeBody()
      .find('.ListTable tr:not(.ListHeader)')
      .then(($el) => {
        return $el.find(':contains("host1")').length > 0;
      });
  });
});

Given('a successfully updated platform', () => {
  cy.waitForContainerAndSetToken();

  cy.loginByTypeOfUser({
    jsonName: 'admin'
  });
});

When('administrator exports Poller configuration', () => {
  cy.addHost({
    checkCommand: 'check_command',
    name: 'host2'
  });

  cy.get('header').get('svg[data-testid="DeviceHubIcon"]').click();

  cy.get('button[data-testid="Export configuration"]').click();

  cy.getByLabel({ label: 'Export & reload', tag: 'button' }).click();

  cy.wait('@generateAndReloadPollers').then(() => {
    cy.contains('Configuration exported and reloaded').should('have.length', 1);
  });
});

Then('Poller configuration should be fully generated', () => {
  checkIfConfigurationIsExported({ dateBeforeLogin, hostName: 'host2' });
});

afterEach(() => {
  cy.stopWebContainer();
});
