import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkIfConfigurationIsExported } from '../../Poller-configuration/common';
import {
  checkPlatformVersion,
  dateBeforeLogin,
  insertHost,
  updatePlatformPackages
} from '../common';

before(() => {
  cy.stopContainer(Cypress.env('dockerName'));
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
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/internal.php?object=centreon_module&action=install&id=centreon-autodiscovery-server&type=module'
  }).as('getAutoDiscoInstallation');
});

Given('a running platform in {string}', (version_A: string) => {
  cy.startContainer({
    name: Cypress.env('dockerName'),
    os: 'legacy-alma8',
    version: version_A
  });

  cy.waitForContainerAndSetToken();

  checkPlatformVersion(version_A);

  cy.visit(`${Cypress.config().baseUrl}`);
});

When('administrator updates packages to {string}', () => {
  updatePlatformPackages();
});

When('administrator runs the update procedure', () => {
  cy.visit(`${Cypress.config().baseUrl}`);

  cy.wait('@getStep1').then(() => {
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep2').then(() => {
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep3').then(() => {
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@generatingCache').then(() => {
    cy.get('span:visible:contains(OK)').should('have.length', 8);
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep5').then(() => {
    cy.get('.btc.bt_success').eq(0).click();
  });
});

Then(
  'monitoring should be up and running after update procedure is complete to {string}',
  (version_B: string) => {
    checkPlatformVersion(version_B);

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
  insertHost();

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
  // cy.stopContainer(Cypress.env('dockerName'));
});
