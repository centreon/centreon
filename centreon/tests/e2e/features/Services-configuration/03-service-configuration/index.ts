/* eslint-disable cypress/unsafe-to-chain-command */
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

When('a service is configured', () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host_1',
    template: 'generic-host'
  }).addService({
    activeCheckEnabled: false,
    host: 'host_1',
    maxCheckAttempts: 1,
    name: 'test',
    template: 'Ping-LAN'
  });
});

When('the user changes the properties of a service', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });

  cy.enterIframe('iframe#main-content')
    .find('table.ListTable')
    .find('tr.list_one')
    .find('td.ListColLeft')
    .contains('test')
    .click();
  cy.enterIframe('iframe#main-content')
    .find('table.formTable')
    .find('tr.list_two')
    .find('td.FormRowValue')
    .find('input[name="service_description"]')
    .clear()
    .type('test_modified');
  cy.enterIframe('iframe#main-content')
    .find('td.FormRowValue')
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
  cy.enterIframe('iframe#main-content')
    .find('table.ListTable')
    .find('tr.list_one')
    .find('td.ListColLeft')
    .contains('test')
    .click();

  cy.enterIframe('iframe#main-content')
    .find('table.formTable')
    .find('tr.list_two')
    .find('td.FormRowValue')
    .find('input[name="service_description"]')
    .should('have.value', 'test_modified');
  cy.enterIframe('iframe#main-content')
    .find('table tr.list_one')
    .find('td.FormRowValue')
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

When('the user duplicates a service', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  cy.getIframeBody().find('input[name="searchH"]').clear().type('host_1');
  cy.getIframeBody().find('input[name="Search"].btc.bt_success').click();
  cy.reload();
  cy.getIframeBody().find('#checkall').click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.exportConfig();
});

Then('the new service has the same properties', () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("test_1")');
  cy.getIframeBody().contains('test_1').click();
  cy.enterIframe('iframe#main-content')
    .find('table.formTable')
    .find('tr.list_two')
    .find('td.FormRowValue')
    .find('input[name="service_description"]')
    .should('have.value', 'test_1');
  cy.getIframeBody()
    .find('table tr.list_one')
    .find('td.FormRowValue')
    .find('select#service_template_model_stm_id')
    .contains('Ping-LAN')
    .should('exist');
});

When('the user deletes a service', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 3,
    subMenu: 'Services'
  });
  cy.enterIframe('iframe#main-content')
    .find('table tbody')
    .find('tr.list_one')
    .each(($row) => {
      cy.wrap($row)
        .find('td.ListColLeft')
        .then(($td) => {
          if ($td.text().includes('host_1')) {
            cy.wrap($row)
              .find('td.ListColPicker')
              .find('div.md-checkbox')
              .click();
          }
        });
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

Then('the deleted service is not displayed in the service list', () => {
  cy.enterIframe('iframe#main-content')
    .find('table.ListTable tbody')
    .contains('test')
    .should('not.exist');
});

afterEach(() => {
  cy.stopContainers();
});
