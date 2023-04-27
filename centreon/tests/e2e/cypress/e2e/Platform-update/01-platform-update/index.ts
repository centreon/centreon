import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkIfConfigurationIsExported } from '../../Poller-configuration/common';
import {
  checkPlatformVersion,
  dateBeforeLogin,
  insertResources,
  installCentreon,
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
});

Given('a running platform in first minor version', () => {
  const version_from = '22.10.0';

  cy.startContainer({
    // image: `docker.centreon.com/centreon/centreon-web-dependencies-alma8:${version_from}`,
    image: 'docker.centreon.com/centreon/centreon-web-dependencies-alma8:23.04',
    name: Cypress.env('dockerName'),
    portBindings: [
      {
        destination: 4000,
        source: 80
      }
    ]
  })
    .then(() => {
      Cypress.config('baseUrl', 'http://localhost:4000');

      return cy
        .intercept('/waiting-page', {
          headers: { 'content-type': 'text/html' },
          statusCode: 200
        })
        .visit('/waiting-page');
    })
    .then(() => {
      installCentreon(version_from).then(() => {
        return checkPlatformVersion(version_from).then(() => cy.visit('/'));
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

  cy.wait('@getStep3').then(() => {
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@generatingCache').then(() => {
    cy.get('span[style]').each(($span) => {
      cy.wrap($span).should('have.text', 'OK');
    });
    cy.get('.btc.bt_info').eq(0).click();
  });

  cy.wait('@getStep5').then(() => {
    cy.get('.btc.bt_success').eq(0).click();
  });
});

Then(
  'monitoring should be up and running after update procedure is complete to current version',
  () => {
    const version_to = '23.04.0';

    cy.setUserTokenApiV1('admin');

    checkPlatformVersion(version_to);

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
