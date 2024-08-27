import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkPlatformVersion,
  getCentreonStableMinorVersions,
  installCentreon
} from '../common';

beforeEach(() => {
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
  (version_from_expression: string) => {
    return cy.getWebVersion().then(({ major_version, minor_version }) => {
      if (minor_version === '0') {
        cy.log(
          `current centreon web version is ${major_version}.${minor_version}, then update cannot be tested`
        );

        return cy.stopContainer({ name: 'web' }).wrap('skipped');
      }

      return getCentreonStableMinorVersions(major_version).then(
        (stable_minor_versions) => {
          if (stable_minor_versions.length === 0) {
            cy.log(`centreon web is currently not available as stable`);

            return cy.stopContainer({ name: 'web' }).wrap('skipped');
          }
          let minor_version_index = 0;
          if (version_from_expression === 'first minor') {
            minor_version_index = 0;
          } else {
            switch (version_from_expression) {
              case 'last stable':
                minor_version_index = stable_minor_versions.length - 1;
                if (
                  stable_minor_versions[minor_version_index] ===
                  Cypress.env('lastStableMinorVersion')
                ) {
                  return cy.stopContainer({ name: 'web' }).wrap('skipped');
                }
                break;
              case 'penultimate stable':
                minor_version_index = stable_minor_versions.length - 2;
                break;
              case 'antepenultimate stable':
                minor_version_index = stable_minor_versions.length - 3;
                break;
              default:
                throw new Error(`${version_from_expression} not managed.`);
            }
            if (minor_version_index <= 0) {
              cy.log(`Not needed to test ${version_from_expression} version.`);

              return cy.stopContainer({ name: 'web' }).wrap('skipped');
            }
          }

          cy.log(
            `${version_from_expression} version is ${stable_minor_versions[minor_version_index]}`
          );

          const installed_version = `${major_version}.${stable_minor_versions[minor_version_index]}`;
          Cypress.env('installed_version', installed_version);
          cy.log('installed_version', installed_version);

          return installCentreon(installed_version).then(() => {
            return checkPlatformVersion(installed_version).then(() =>
              cy.visit('/')
            );
          });
        }
      );
    });
  }
);

afterEach(() => {
  cy.visitEmptyPage().stopContainer({ name: 'web' });
});
