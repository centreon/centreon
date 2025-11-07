import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import serviceMacros from '../../../fixtures/macros/services.json';

const clickToAddService = () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );
};

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/ac-acl-user.json'
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
    jsonName: 'user-non-admin-for-AC',
    loginViaApi: false
  });
});

Given('the non-admin user is on the "Configuration > services" page', () => {
  cy.visitServicesListing(0);
});

When('the non-admin user clicks to add a new service', () => {
  clickToAddService();
});

When('the non-admin user fills in all mandatory fields', () => {
  cy.fillServiceMandatoryField(
    serviceMacros.default_service.name,
    serviceMacros.default_service.host,
    serviceMacros.default_service.cmd
  );
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
   cy.checkMacrosFromTheExportFile(
      fileName,
      false,
      serviceMacros.default_service.normalMacro,
      serviceMacros.default_service.passMacro
    );
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
    cy.visitServiceTemplatesListing(0);
  }
);

Given(
  'a service Template {string} exists with defined normal and password macros',
  (name: string) => {
    clickToAddService();
    // Fill mandatory fields
    cy.getIframeBody()
      .find('input[name="service_description"]')
      .clear()
      .type(name);
    cy.getIframeBody().find('input[name="service_alias"]').clear().type(name);
    // Fill Service Template Macros (one normal, one of type password)
    cy.fillMacros(
      false,
      serviceMacros.default_service.normalMacro,
      serviceMacros.default_service.passMacro
    );
    // Save the configuration
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    // Wait until the service template is charged on the DOM
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
  }
);

Given(
  'a pre-configured service using {string} as its parent template',
  (parent: string) => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${serviceMacros.default_service.name})`
    );
    cy.getIframeBody()
      .contains('a', serviceMacros.default_service.name)
      .click();
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="service_description"]'
    );
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
  }
);

Then(
  'the macros should be stored in the service Template configuration file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      false,
      serviceMacros.default_service.normalMacro,
      serviceMacros.default_service.passMacro
    );
  }
);

Then(
  'the service configuration file {string} should not contain the inherited macros',
  (file: string) => {
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
  }
);

Given(
  'a pre-configured Service Template {string} that contains defined macros',
  (name: string) => {
    cy.waitForElementInIframe('#main-content', `a:contains("${name}")`);
  }
);

When(
  'the non-admin user creates a new Service Template {string} with {string} as its parent',
  (child: string, parent: string) => {
    clickToAddService();
    // Fill mandatory fields
    cy.getIframeBody()
      .find('input[name="service_description"]')
      .clear()
      .type(child);
    cy.getIframeBody().find('input[name="service_alias"]').clear().type(child);
    // Add the parent service template to the child service
    cy.getIframeBody().find('span[role="presentation"]').eq(0).click();
    cy.getIframeBody().find(`div[title="${parent}"]`).click();
  }
);

When(
  'the non-admin user changes the value of the normal macro inherited from Service Template "ST-A"',
  () => {
    // Check first that the inherited macros are visible
    [0, 1].forEach((index) => {
      cy.getIframeBody().find(`#macroInput_${index}`).should('be.visible');
    });
    // Check that the inherited macros are highlighted in orange
    [0, 1].forEach((index) => {
      cy.getIframeBody()
        .find(`#macroInput_${index}`)
        .should('have.attr', 'style')
        .and('include', 'var(--custom-macros-template-background-color)');
    });
    // Now change the normal macro value
    cy.getIframeBody()
      .find('#macroValue_0')
      .clear()
      .type(`${serviceMacros.updated_service.normalMacro.value}`);
  }
);

Then(
  'the normal macro value in {string} should be the modified value',
  (name: string) => {
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="service_description"]'
    );
    cy.getIframeBody()
      .find('#macroValue_0')
      .should(
        'have.value',
        `${serviceMacros.updated_service.normalMacro.value}`
      );
  }
);

Then('the normal macro should not be highlighted in orange', () => {
  cy.getIframeBody().find('#macroInput_0').should('not.have.attr', 'style');
});

Then('the password macro should still be highlighted in orange', () => {
  cy.getIframeBody()
    .find('#macroInput_1')
    .should('have.attr', 'style')
    .and('include', 'var(--custom-macros-template-background-color)');
});

When(
  'the user creates a new Service {string} using {string} as its parent template',
  (child: string, parent: string) => {
    cy.visitServicesListing(0);
    clickToAddService();
    cy.fillServiceMandatoryField(
      child,
      serviceMacros.default_service.host,
      serviceMacros.default_service.cmd
    );
    // Add the parent service template to the child service
    cy.getIframeBody().find('span[role="presentation"]').eq(1).click();
    cy.getIframeBody().find(`div[title="${parent}"]`).click();
  }
);

Then(
  'the macro values in Service Template {string} should remain unchanged',
  (name: string) => {
    cy.visitServicesListing(0);
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="service_description"]'
    );
    cy.getIframeBody()
      .find('#macroValue_0')
      .should(
        'have.value',
        `${serviceMacros.default_service.normalMacro.value}`
      );
  }
);

Given(
  'a pre-configured Service using a service template with defined macros as its parent template',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${serviceMacros.default_service.name})`
    );
  }
);

When(
  'the normal macro value in the service should be the modified value',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${serviceMacros.default_service.name})`
    );
    cy.getIframeBody()
      .contains('a', serviceMacros.default_service.name)
      .click();
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="service_description"]'
    );
    cy.getIframeBody()
      .find('#macroValue_0')
      .should(
        'have.value',
        `${serviceMacros.updated_service.normalMacro.value}`
      );
  }
);

Then(
  'the new value of the inherited normal macro is exported to the file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      true,
      serviceMacros.updated_service.normalMacro,
      serviceMacros.updated_service.passMacro
    );
  }
);

Then(
  'the old values of macros are exported to the service template file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      false,
      serviceMacros.default_service.normalMacro,
      serviceMacros.default_service.passMacro
    );
  }
);
