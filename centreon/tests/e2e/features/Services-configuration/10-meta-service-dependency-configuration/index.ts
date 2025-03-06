/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/services/meta_service.json';

beforeEach(() => {
  cy.startContainers();
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
    url: '/entreon/include/common/webServices/rest/internal.php?object=centreon_configuration_meta&action=list&*'
  }).as('getListOfMServices');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some meta services are configured', () => {
  cy.navigateTo({
    page: 'Meta Services',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
  cy.addMetaService(data.metaService1);
  cy.addMetaService(data.metaService2);
  cy.addMetaService(data.metaService3);
});

Given('a meta service dependency is configured', () => {
  cy.visit('/').url().should('include', '/monitoring/resources');
  cy.navigateTo({
    page: 'Meta Services',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
  cy.getIframeBody().contains('a', 'Add').click({ force: true });
  cy.wait('@getTimeZone');
  cy.addMSDependency(data.defaultMetaServiceDep);
});

When(
  'the user changes the properties of the configured meta service dependency',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${data.defaultMetaServiceDep.name}")`
    );
    cy.getIframeBody().contains(data.defaultMetaServiceDep.name).click();
    cy.updateMSDependency(data.MetaServiceDep1);
  }
);

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.MetaServiceDep1.name}")`
  );
  cy.getIframeBody().contains(data.MetaServiceDep1.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.MetaServiceDep1.name);

  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.MetaServiceDep1.description);
  cy.getIframeBody().find('#eOk').should('be.checked');
  cy.getIframeBody().find('#nCritical').should('be.checked');
  cy.getIframeBody()
    .find('#dep_msParents')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.metaService2.name);
  cy.getIframeBody()
    .find('#dep_msChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.metaService1.name);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.MetaServiceDep1.comment);
});

When('the user duplicates the configured meta service dependency', () => {
  cy.checkFirstRowFromListing('searchMSD');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
});

Then(
  'a new meta service dependency is created with identical properties',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${data.defaultMetaServiceDep.name}_1")`
    );
    cy.getIframeBody().contains(`${data.defaultMetaServiceDep.name}_1`).click();
    cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody()
      .find('input[name="dep_name"]')
      .should('have.value', `${data.defaultMetaServiceDep.name}_1`);
    cy.getIframeBody()
      .find('input[name="dep_description"]')
      .should('have.value', data.defaultMetaServiceDep.description);
    cy.getIframeBody().find('#eUnknown').should('be.checked');
    cy.getIframeBody().find('#nUnknown').should('be.checked');
    cy.getIframeBody()
      .find('#dep_msParents')
      .find('option:selected')
      .should('have.length', 2)
      .then((options) => {
        const selectedTexts = Array.from(options).map((option) =>
          option.text.trim()
        );
        expect(selectedTexts).to.include.members([
          data.metaService1.name,
          data.metaService2.name
        ]);
      });
    cy.getIframeBody()
      .find('#dep_msChilds')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', data.metaService3.name);
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .should('have.value', data.defaultMetaServiceDep.comment);
  }
);

When('the user deletes the configured meta service dependency', () => {
  cy.checkFirstRowFromListing('searchMSD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
});

Then(
  'the deleted meta service dependency is not displayed in the list of meta service dependencies',
  () => {
    cy.reload();
    cy.wait('@getTimeZone');
    cy.getIframeBody()
      .contains(data.defaultMetaServiceDep.name)
      .should('not.exist');
  }
);
