/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import contactTemplates from '../../../fixtures/users/contact.json';

const checkFirstContactTemplateFromListing = () => {
  cy.navigateTo({
    page: 'Contact Templates',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

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
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod*'
  }).as('getTimePeriods');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_command*'
  }).as('getNotCommands');
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

When('a contact template is configured', () => {
  cy.navigateTo({
    page: 'Contact Templates',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateContactTemplate(contactTemplates.defaultTemplate);
});

When(
  'the user updates the properties of the configured contact template',
  () => {
    cy.getIframeBody().contains(contactTemplates.defaultTemplate.alias).click();
    cy.addOrUpdateContactTemplate(contactTemplates.templateForUpdate);
  }
);

Then('the properties are updated', () => {
  cy.getIframeBody()
    .contains(contactTemplates.templateForUpdate.alias)
    .should('exist');
  cy.getIframeBody().contains(contactTemplates.templateForUpdate.alias).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');
  cy.getIframeBody()
    .find('input[name="contact_alias"]')
    .should('have.value', contactTemplates.templateForUpdate.alias);
  cy.getIframeBody()
    .find('input[name="contact_name"]')
    .should('have.value', contactTemplates.templateForUpdate.name);
  cy.getIframeBody()
    .find('select[name="contact_template_id"]')
    .should('have.value', contactTemplates.templateForUpdate.usedCTemplate);
  cy.getIframeBody()
    .find('select[name="default_page"]')
    .should('have.value', contactTemplates.templateForUpdate.defaultPage);
  cy.checkLegacyRadioButton(contactTemplates.templateForUpdate.isNotEnabled);
  cy.getIframeBody().find('input[id="hDown"]').should('not.be.checked');
  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should(
      'have.attr',
      'title',
      contactTemplates.templateForUpdate.timePeriod
    );
  cy.getIframeBody()
    .find('#contact_hostNotifCmds')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands,
        contactTemplates.templateForUpdate.NotCommands
      ]);
    });
  cy.getIframeBody().find('input[id="sWarning"]').should('not.be.checked');
  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .should(
      'have.attr',
      'title',
      contactTemplates.templateForUpdate.timePeriod
    );
  cy.getIframeBody()
    .find('#contact_svNotifCmds')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands,
        contactTemplates.templateForUpdate.NotCommands
      ]);
    });
});

When('the user duplicates the configured contact template', () => {
  checkFirstContactTemplateFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact template is created with identical properties', () => {
  cy.getIframeBody()
    .contains(`${contactTemplates.defaultTemplate.alias}_1`)
    .should('exist');
  cy.getIframeBody()
    .contains(`${contactTemplates.defaultTemplate.alias}_1`)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');
  cy.getIframeBody()
    .find('input[name="contact_alias"]')
    .should('have.value', `${contactTemplates.defaultTemplate.alias}_1`);
  cy.getIframeBody()
    .find('input[name="contact_name"]')
    .should('have.value', `${contactTemplates.defaultTemplate.name}_1`);
  cy.getIframeBody()
    .find('select[name="contact_template_id"]')
    .should('have.value', contactTemplates.defaultTemplate.usedCTemplate);
  cy.getIframeBody()
    .find('select[name="default_page"]')
    .should('have.value', contactTemplates.defaultTemplate.defaultPage);
  cy.checkLegacyRadioButton(contactTemplates.defaultTemplate.isNotEnabled);
  cy.getIframeBody().find('input[id="hDown"]').should('be.checked');
  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', contactTemplates.defaultTemplate.timePeriod);
  cy.getIframeBody()
    .find('#contact_hostNotifCmds')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands
      ]);
    });
  cy.getIframeBody().find('input[id="sWarning"]').should('be.checked');
  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .should('have.attr', 'title', contactTemplates.defaultTemplate.timePeriod);
  cy.getIframeBody()
    .find('#contact_svNotifCmds')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands
      ]);
    });
});

When('the user deletes the configured contact template', () => {
  checkFirstContactTemplateFromListing();
  cy.getIframeBody().find('select[name="o1"').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted contact template is not visible anymore on the contact template page',
  () => {
    cy.getIframeBody()
      .contains(contactTemplates.defaultTemplate.alias)
      .should('not.exist');
  }
);
