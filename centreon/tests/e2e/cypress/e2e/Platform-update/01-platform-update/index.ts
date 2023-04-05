import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkIfConfigurationIsExported,
  insertHost
} from '../../Poller-configuration/common';
import {
  checkIfSystemUserRoot,
  checkPlatformVersion,
  givingPermissionsToCacheFolder,
  injectingModulesLicense,
  setUserAdminDefaultCredentials,
  updatePlatformPackages
} from '../common';

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

Given('an admin user with valid non-default credentials', () => {
  setUserAdminDefaultCredentials();
});

Given('a system user root', () => {
  checkIfSystemUserRoot();
});

Given(
  'a running platform in {string} with all extensions installed',
  (version_A: string) => {
    checkPlatformVersion(version_A);

    givingPermissionsToCacheFolder();

    injectingModulesLicense();

    cy.visit(`${Cypress.config().baseUrl}`);

    cy.loginByTypeOfUser({ jsonName: 'admin' }).wait('@getNavigationList');

    cy.navigateTo({
      page: 'Extensions',
      rootItemNumber: 4,
      subMenu: 'Manager'
    });

    cy.get('button[class="MuiButtonBase-root"')
      .eq(6)
      .click()
      .wait('@getAutoDiscoInstallation');
  }
);

Given(
  'this platform has existing configuration for all the installed extensions',
  () => {
    // TODO
  }
);

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
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep5').then(() => {
    cy.get('.btc.bt_success').eq(0).click();
  });
});

Then('monitoring should be up and running after procedure is complete', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin-with-nondefault-credentials'
  });

  cy.url().should('include', '/monitoring/resources');

  cy.wait('@monitoringEndpoint').its('response.statusCode').should('eq', 200);

  cy.setUserTokenApiV1('admin-with-nondefault-credentials.json');
});

Given('a successfully updated platform', () => {
  cy.visit(`${Cypress.config().baseUrl}`);

  cy.loginByTypeOfUser({
    jsonName: 'admin-with-nondefault-credentials'
  });
});

When('administrator exports Poller configuration', () => {
  insertHost();

  cy.get('header').get('svg[data-testid="DeviceHubIcon"]').click();

  cy.get('button[data-testid="Export configuration"]').click();

  cy.getByLabel({ label: 'Export & reload', tag: 'button' }).click();

  cy.wait('@generateAndReloadPollers').then(() => {
    cy.get('.SnackbarContent-root > .MuiPaper-root')
      .contains('Configuration exported and reloaded')
      .should('have.length', 1);
  });

  checkIfConfigurationIsExported();
});
