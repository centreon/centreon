import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkPlatformVersion,
  getCentreonStableMinorVersions,
  installCentreon
} from '../common';

beforeEach(() => {
  // clear network cache to avoid chunk loading issues
  cy.wrap(
    Cypress.automation('remote:debugger:protocol', {
      command: 'Network.clearBrowserCache'
    })
  );

  cy.getWebVersion().then(({ major_version }) => {
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
        command: 'tail -f /dev/null',
        image: `docker.centreon.com/centreon/centreon-web-dependencies-${Cypress.env(
          'WEB_IMAGE_OS'
        )}:${major_version}`,
        name: 'web',
        portBindings: [
          {
            destination: 4000,
            source: 80
          }
        ]
      })
      .then(() => {
        Cypress.config('baseUrl', 'http://127.0.0.1:4000');

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
  'a running platform in {string} version',
  (versionFromExpression: string) => {
    return cy.getWebVersion().then(({ major_version, minor_version }) => {
      if (minor_version === '0') {
        cy.log(
          `current centreon web version is ${major_version}.${minor_version}, then update cannot be tested`
        );

        return cy.stopContainer({ name: 'web' }).wrap('skipped');
      }

      return getCentreonStableMinorVersions(major_version).then(
        (stableMinorVersions) => {
          if (stableMinorVersions.length === 0) {
            cy.log('centreon web is currently not available as stable');

            return cy.stopContainer({ name: 'web' }).wrap('skipped');
          }
          let minorVersionIndex = 0;
          if (versionFromExpression === 'first minor') {
            minorVersionIndex = 0;
          } else {
            switch (versionFromExpression) {
              case 'last stable':
                minorVersionIndex = stableMinorVersions.length - 1;
                if (
                  stableMinorVersions[minorVersionIndex] ===
                  Cypress.env('lastStableMinorVersion')
                ) {
                  return cy.stopContainer({ name: 'web' }).wrap('skipped');
                }
                break;
              case 'penultimate stable':
                minorVersionIndex = stableMinorVersions.length - 2;
                break;
              case 'antepenultimate stable':
                minorVersionIndex = stableMinorVersions.length - 3;
                break;
              default:
                throw new Error(`${versionFromExpression} not managed.`);
            }
            if (minorVersionIndex <= 0) {
              cy.log(`Not needed to test ${versionFromExpression} version.`);

              return cy.stopContainer({ name: 'web' }).wrap('skipped');
            }
          }

          cy.log(
            `${versionFromExpression} version is ${stableMinorVersions[minorVersionIndex]}`
          );

          const installedVersion = `${major_version}.${stableMinorVersions[minorVersionIndex]}`;
          Cypress.env('installed_version', installedVersion);
          cy.log('installed_version', installedVersion);

          return installCentreon(installedVersion).then(() => {
            return checkPlatformVersion(installedVersion).then(() =>
              cy.visit('/')
            );
          });
        }
      );
    });
  }
);

afterEach(() => {
  cy.visitEmptyPage()
    .copyWebContainerLogs({ name: 'web' })
    .stopContainer({ name: 'web' });
});
