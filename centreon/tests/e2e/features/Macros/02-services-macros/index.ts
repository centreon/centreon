import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import serviceMacros from '../../../fixtures/macros/services.json';

const clickToAddService = () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', "Add").click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
}

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/contacts-management-acl-user.json'
  );
});

beforeEach(() => {
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
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_host&*'
  }).as('getHostsList');
   cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('exportConf');
});

after(() => {
  cy.stopContainers();
});

Given('a non-admin user is logged into the Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'contacts-management-acl-user',
    loginViaApi: false
  });
});

Given('the non-admin user is on the "Configuration > services" page', () => {
  cy.navigateTo({
    page: 'Services by host',
    rootItemNumber: 0,
    subMenu: 'Services'
  });
  cy.wait('@getTimeZone');
});

When('the non-admin user clicks to add a new service', () => {
  clickToAddService();
});

When('the non-admin user fills in all mandatory fields', () => {
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .clear()
    .type(serviceMacros.default_service.name);
  cy.getIframeBody().find('input[placeholder="Hosts"]').click();
  cy.wait('@getHostsList');
  cy.getIframeBody()
    .contains('div', serviceMacros.default_service.host)
    .click();
  cy.getIframeBody().find('span[title="Check Command"]').click();
  cy.getIframeBody().contains('div', serviceMacros.default_service.cmd).click();
});

When('the non-admin user adds one normal macro and one password macro', () => {
  cy.fillMacros(
    false,
    serviceMacros.default_service.normalMacro,
    serviceMacros.default_service.passMacro
  );
});

When('the non-admin user clicks the "Save" button', () => {
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('all the properties, including the macros, are successfully saved', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${serviceMacros.default_service.name})`
  );
  cy.getIframeBody().contains('a', serviceMacros.default_service.name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', serviceMacros.default_service.name);
  cy.checkMacrosFieldsValues(
    serviceMacros.default_service.normalMacro,
    serviceMacros.default_service.passMacro
  );
});

Then('the export configuration is done with success', () => {
  cy.exportConfig();
  cy.wait('@exportConf').its('response.statusCode').should('eq', 204);
});

Then('the macros are exported to the file {string}', (fileName: string) => {
  cy.execInContainer({
    command: `cat ${fileName}`,
    name: 'web'
  }).then((result) => {
    expect(result.exitCode).to.eq(0);
    const output = result.output;
    const regexNormal = new RegExp(
      `${serviceMacros.default_service.normalMacro.name}\\s+raw::${serviceMacros.default_service.normalMacro.value}`
    );
    expect(output).to.match(regexNormal);
    const regexPassword = new RegExp(
      `${serviceMacros.default_service.passMacro.name}\\s+encrypt::[A-Za-z0-9+/=]+`
    );
    expect(output).to.match(regexPassword);
  });
});

Given('an existing service with macros', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${serviceMacros.default_service.name})`
  );
});

When('the non-admin user opens the service for editing', () => {
  cy.getIframeBody().contains('a', serviceMacros.default_service.name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
});

When('the non-admin user updates the values of the existing macros', () => {
  cy.fillMacros(
    true,
    serviceMacros.updated_service.normalMacro,
    serviceMacros.updated_service.passMacro
  );
});

Then('the modified macros are saved successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${serviceMacros.updated_service.name})`
  );
  cy.getIframeBody().contains('a', serviceMacros.updated_service.name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  cy.checkMacrosFieldsValues(
    serviceMacros.updated_service.normalMacro,
    serviceMacros.updated_service.passMacro
  );
});

Given('a configured service with macros', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${serviceMacros.updated_service.name})`
  );
});

When('the non-admin user deletes the macros of the configured service', () => {
  cy.getIframeBody().contains('a', serviceMacros.updated_service.name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  // Remove the normal macro
  cy.getIframeBody().find('#macro_remove_current').eq(0).click();
  // Remove tha password macro
  cy.getIframeBody().find('#macro_remove_current').eq(0).click();
});

Then('the macros are deleted successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${serviceMacros.updated_service.name})`
  );
  cy.getIframeBody().contains('a', serviceMacros.updated_service.name).click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
  // Check the non-existence of the Macros
  cy.getIframeBody()
    .contains(serviceMacros.updated_service.normalMacro.name)
    .should('not.exist');
  cy.getIframeBody()
    .contains(serviceMacros.updated_service.passMacro.name)
    .should('not.exist');
});

Then('the macros are removed from the file {string}', (fileName: string) => {
  cy.execInContainer({
    command: `cat ${fileName}`,
    name: 'web'
  }).then((result) => {
    expect(result.exitCode).to.eq(0);
    const output = result.output;
    const regexNormal = new RegExp(
      `${serviceMacros.updated_service.normalMacro.name}`
    );
    expect(output).not.to.match(regexNormal);
    const regexPassword = new RegExp(
      `${serviceMacros.updated_service.passMacro.name}`
    );
    expect(output).not.to.match(regexPassword);
  });
});

Given(
  'a non-admin user is on the "Configuration > services > Templates" page',
  () => {
    cy.navigateTo({
      page: 'Templates',
      rootItemNumber: 0,
      subMenu: 'Services'
    });
    cy.wait('@getTimeZone');
  }
);

Given('a service Template {string} exists with defined normal and password macros', (name: string) => {
  clickToAddService();
  // Fill mandatory fields
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .clear()
    .type(name);
  cy.getIframeBody()
    .find('input[name="service_alias"]')
    .clear()
    .type(name);
  // Fill Service Template Macros (one normal, one of type password)
  cy.fillMacros(false, serviceMacros.default_service.normalMacro, serviceMacros.default_service.passMacro);
  // Save the configuration
  cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
  cy.wait('@getTimeZone');
  // Wait until the service template is charged on the DOM
  cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
});

Given('a pre-configured service using {string} as its parent template', (parent: string) => {
  cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${serviceMacros.default_service.name})`
    );
    cy.getIframeBody().contains('a', serviceMacros.default_service.name).click();
    cy.waitForElementInIframe('#main-content', 'input[name="service_description"]');
    // Add the service template to the service
    cy.getIframeBody().find('span[role="presentation"]').eq(1).click();
    cy.getIframeBody().find(`div[title="${parent}"]`).click();
    // Save the configuration
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    // Wait until the service is charged on the DOM page
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${serviceMacros.default_service.name})`
    );
});

Then('the macros should be stored in the service Template configuration file {string}', (file: string) => {
   cy.checkMacrosFromTheExportFile(
      file,
      false,
      serviceMacros.default_service.normalMacro,
      serviceMacros.default_service.passMacro
    );
});

Then('the service configuration file {string} should not contain the inherited macros', (file: string) => {
  cy.execInContainer({
      command: `cat ${file}`,
      name: 'web'
    }).then((result) => {
      expect(result.exitCode).to.eq(0);
      const output = result.output;
      const regexNormal = new RegExp(
        `${serviceMacros.default_service.normalMacro.name}`
      );
      expect(output).not.to.match(regexNormal);
      const regexPassword = new RegExp(
        `${serviceMacros.default_service.passMacro.name}`
      );
      expect(output).not.to.match(regexPassword);
    });
})



