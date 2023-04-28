import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkIfConfigurationIsExported } from '../../Poller-configuration/common';
import {
  checkPlatformVersion,
  dateBeforeLogin,
  insertResources,
  installCentreon,
  updatePlatformPackages
} from '../common';

before(() => {
  cy.getWebVersion().then(({ major_version, minor_version }) => {
    if (minor_version === '0') {
      cy.log(
        `current centreon web version is ${major_version}.${minor_version}, then update cannot be tested`
      );

      return Cypress.runner.stop();
    }

    return cy
      .startContainer({
        image: `docker.centreon.com/centreon/centreon-web-dependencies-alma8:${major_version}`,
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

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

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

Given('a running platform in first minor version', () => {
  cy.getWebVersion().then(({ major_version }) => {
    installCentreon(`${major_version}.0`).then(() => {
      return checkPlatformVersion(`${major_version}.0`).then(() =>
        cy.visit('/')
      );
    });
  });
});

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
    .get('span[style]')
    .each(($span) => {
      cy.wrap($span).should('have.text', 'OK');
    });
  cy.get('.btc.bt_info').eq(0).click();

  cy.wait('@getStep5').get('.btc.bt_success').eq(0).click();
});

Then(
  'monitoring should be up and running after update procedure is complete to current version',
  () => {
    cy.setUserTokenApiV1('admin');

    cy.loginByTypeOfUser({
      jsonName: 'admin'
    });

    cy.url().should('include', '/monitoring/resources');

    cy.wait('@monitoringEndpoint').its('response.statusCode').should('eq', 200);

    cy.setUserTokenApiV1('admin');
  }
);

Given('a successfully updated platform', () => {
  cy.waitForContainerAndSetToken();

  cy.loginByTypeOfUser({
    jsonName: 'admin'
  });
});

When('administrator exports Poller configuration', () => {
  insertResources();

  cy.get('header').get('svg[data-testid="DeviceHubIcon"]').click();

  cy.get('button[data-testid="Export configuration"]').click();

  cy.getByLabel({ label: 'Export & reload', tag: 'button' }).click();

  cy.wait('@generateAndReloadPollers').then(() => {
    cy.contains('Configuration exported and reloaded').should('have.length', 1);
  });
});

Then('Poller configuration should be fully generated', () => {
  checkIfConfigurationIsExported(dateBeforeLogin);
});

after(() => {
  cy.stopContainer({ name: Cypress.env('dockerName') });
});
