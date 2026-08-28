import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'commons';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const services = {
  serviceCritical: {
    host: 'host2',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  }
];

const checkServicesProperties = (name) => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  // Not a plain click: this helper runs three times in a row, and each run
  // submits the form, which closes the panel. closePanel() only resets the
  // iframe src 300ms later, so clicking inside that window lets the pending
  // timeout overwrite the src cfOpenPanel just set and the panel loads blank
  // for good. openListingRowForm waits for that reset before opening the next.
  cy.openListingRowForm(name);
  cy.getFormBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible');

  cy.getFormBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');
  cy.getFormBody()
    .find('input[name="service_max_check_attempts"]')
    .should('have.value', '2');
  cy.getFormBody()
    .find('input[name="service_retry_check_interval"]')
    .should('have.value', '3');
  cy.getFormBody().find('input.btc.bt_success[name^="submit"]').first().click();
  cy.wait('@getTimeZone');
};

// Select the row of a named service. The listing groups its rows by host, so
// positional indexes no longer identify a service.
const selectServiceRow = (name: string): void => {
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${name}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
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

Given('several services have been created with mandatory properties', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
});

When('the user has applied "Mass Change" operation on several services', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  selectServiceRow(services.serviceOk.name);
  selectServiceRow(services.serviceWarning.name);
  selectServiceRow(services.serviceCritical.name);

  // Mass Change is the one bulk action that does not submit the form: the
  // framework opens the side panel on the selected ids instead, so there is
  // neither a confirmation modal nor a POST — hence the menu driven here rather
  // than cy.runListingBulkAction(). Selecting on the hidden <select> bypasses
  // the menu interception entirely and submits the whole page, and that reload
  // detaches the subject of whatever came next.
  // The panel then loads the mass change form in its iframe, and that form is
  // heavy: getFormBody() only waits 20s for a body to stop being empty, which a
  // busy CI platform outruns. Synchronising on the request itself removes the
  // guesswork.
  cy.intercept('GET', '**/main.get.php*o=mc*').as('massChangeForm');
  cy.getIframeBody().find('.cl-more-actions-btn').first().click();
  cy.getIframeBody().find('.cl-more-actions-item[data-value="mc"]').click();
  cy.wait('@massChangeForm', { timeout: 60_000 });
  // The request landing is not the panel being ready: its iframe still has to
  // parse and run a form of that size. getFormBody() allows 20s for the body to
  // fill, which the runner outruns, so the wait is made explicit and generous
  // here rather than by loosening the shared helper for every suite.
  cy.get('iframe#main-content', { log: false })
    .its('0.contentDocument.body', { timeout: 60_000 })
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 60_000 })
    .should('not.be.empty');
  cy.wait('@getTimeZone');
  cy.getFormBody()
    .find('span[id="select2-command_command_id-container"]', {
      timeout: 20_000
    })
    .click();
  cy.getFormBody().find('div[title="check_http"]').click();
  cy.getFormBody().find('input[name="service_max_check_attempts"]').type('2');
  cy.getFormBody().find('input[name="service_retry_check_interval"]').type('3');
  cy.getFormBody()
    .find('input.btc.bt_success[name="submitMC"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('all selected services are updated with the same values', () => {
  checkServicesProperties(services.serviceOk.name);
  checkServicesProperties(services.serviceWarning.name);
  checkServicesProperties(services.serviceCritical.name);
});
