import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getUserTimezone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Then('a service template is configured', () => {
  cy.addServiceTemplate({
    name: 'service_template',
    template: 'generic-service'
  });
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody().contains('service_template').click();
  cy.waitForElementInIframe('#main-content', 'input[name="service_alias"]');
  cy.getIframeBody()
    .find('input[name="service_alias"]')
    .clear()
    .type('service_template');
  cy.getIframeBody()
    .find('div#validForm')
    .find('p.oreonbutton')
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

When('the user changes the properties of a service template', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody().contains('service_template').click();
  cy.waitForElementInIframe('#main-content', 'input[name="service_alias"]');
  cy.getIframeBody()
    .find('input[name="service_alias"]')
    .clear()
    .type('service_template_modified');
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .clear()
    .type('template_desp_modified');
  cy.getIframeBody()
    .find('select#service_template_model_stm_id')
    .next()
    .click();
  cy.getIframeBody().contains('Ping-WAN').click();
  //Click on the tab 'Notifications'
  cy.getIframeBody().contains('a', 'Notifications').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Chose '24x7' as Notification Period
  cy.getIframeBody().find('#select2-timeperiod_tp_id2-container').click();
  cy.getIframeBody().contains('div', '24x7').click();
  // Check 'Critical' as Notification type
  cy.getIframeBody().find('#notifC').click({ force: true });
  //Click on the 'Save' button
  cy.getIframeBody()
    .find('div#validForm')
    .find('p.oreonbutton')
    .find('.btc.bt_success[name="submitC"]')
    .click();
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody().contains('service_template_modified').click();
  cy.waitForElementInIframe('#main-content', 'input[name="service_alias"]');
  cy.getIframeBody()
    .find('input[name="service_alias"]')
    .should('have.value', 'service_template_modified');
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', 'template_desp_modified');
  cy.getIframeBody()
    .find('select#service_template_model_stm_id')
    .contains('Ping-WAN')
    .should('exist');
  //Click on the tab 'Notifications'
  cy.getIframeBody().contains('a', 'Notifications').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the 'Notification Period' has the setted value
  cy.getIframeBody()
      .find('#timeperiod_tp_id2')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', '24x7');
  // Check that the type 'Critical' is checked
  cy.getIframeBody().find('#notifC').should('be.checked');
});

When('the user duplicates a service template', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody()
    .find('td.ListColLeft')
    .contains('a', 'service_template')
    .parents('tr')
    .within(() => {
      cy.get('td.ListColPicker').find('div.md-checkbox').click();
    });
  cy.enterIframe('iframe#main-content')
    .find('table.ToolbarTable tbody')
    .find('td.Toolbar_TDSelectAction_Bottom')
    .find('select')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }"
    );
  cy.enterIframe('iframe#main-content')
    .find('table.ToolbarTable tbody')
    .find('td.Toolbar_TDSelectAction_Bottom')
    .find('select')
    .select('Duplicate');
});

Then('the new service template has the same properties', () => {
  cy.reload();
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody()
    .find('td.ListColLeft')
    .contains('a', 'service_template_1')
    .click();
  cy.waitForElementInIframe('#main-content', 'input[name="service_alias"]');
  cy.getIframeBody()
    .find('input[name="service_alias"]')
    .should('have.value', 'service_template');
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', 'service_template_1');
});

When('the user deletes a service template', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchST"]');
  cy.getIframeBody()
    .find('td.ListColLeft')
    .contains('a', 'service_template')
    .parents('tr')
    .within(() => {
      cy.get('td.ListColPicker').find('div.md-checkbox').click();
    });
  cy.enterIframe('iframe#main-content')
    .find('table.ToolbarTable tbody')
    .find('td.Toolbar_TDSelectAction_Bottom')
    .find('select')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }"
    );
  cy.enterIframe('iframe#main-content')
    .find('table.ToolbarTable tbody')
    .find('td.Toolbar_TDSelectAction_Bottom')
    .find('select')
    .select('Delete');
});

Then('the deleted service template is not displayed in the list', () => {
  cy.enterIframe('iframe#main-content')
    .find('table.ListTable tbody')
    .contains('service_template')
    .should('not.exist');
});

afterEach(() => {
  cy.stopContainers();
});
