import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import hostMacros from '../../../fixtures/macros/hosts.json';
import { getFormBody } from '../commands';

/**
 * The host and host template forms open in the side panel now, so the form is a
 * nested iframe and waitForElementInIframe('#main-content', ...) cannot reach
 * it. `exist` rather than `be.visible`, so this also holds for a frozen form.
 */
const waitForHostForm = () => {
  cy.getSidePanelBody()
    .find('input[name="host_name"]', { timeout: 20_000 })
    .should('exist');
};

const clickToAddHost = () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', 'Add').click();
  waitForHostForm();
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
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.generate_reload_pollers
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

Given('the non-admin user is on the "Configuration > Hosts" page', () => {
  cy.visitHostsListingPage(0);
});

When('the non-admin user clicks to add a new host', () => {
  clickToAddHost();
});

When('the non-admin user fills in all mandatory fields', () => {
  cy.fillHostBasicsInfos(
    hostMacros.default_host.name,
    hostMacros.default_host.alias
  );
  getFormBody()
    .find('input[name="host_address"]')
    .clear()
    .type(hostMacros.default_host.address);
  // The multi-select's inline search carries the generic "Search" placeholder
  // now that the label moved to the floating label, so open it via its .cf-field.
  getFormBody()
    .contains('.cf-field', 'ACL Resource Groups')
    .find('.select2-selection')
    .click();
  getFormBody().contains('div', 'user-ACLGROUP').click();
});

When('the non-admin user adds one normal macro and one password macro', () => {
  cy.fillMacros(
    false,
    hostMacros.default_host.normalMacro,
    hostMacros.default_host.passMacro
  );
});

When('the non-admin user clicks the "Save" button', () => {
  getFormBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('all the properties, including the macros, are successfully saved', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.default_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.default_host.name).click();
  waitForHostForm();
  getFormBody()
    .find('input[name="host_name"]')
    .should('have.value', hostMacros.default_host.name);
  getFormBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostMacros.default_host.alias);
  getFormBody()
    .find('input[name="host_address"]')
    .should('have.value', hostMacros.default_host.address);
  cy.checkMacrosFieldsValues(
    hostMacros.default_host.normalMacro,
    hostMacros.default_host.passMacro
  );
});

Then('the macros are exported to the file {string}', (fileName: string) => {
  cy.checkMacrosFromTheExportFile(
    fileName,
    false,
    hostMacros.default_host.normalMacro,
    hostMacros.default_host.passMacro
  );
});

Given('an existing host with macros', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.default_host.name})`
  );
});

When('the non-admin user opens the host for editing', () => {
  cy.getIframeBody().contains('a', hostMacros.default_host.name).click();
  waitForHostForm();
});

When('the non-admin user updates the values of the existing macros', () => {
  cy.fillMacros(
    true,
    hostMacros.updated_host.normalMacro,
    hostMacros.updated_host.passMacro
  );
});

Then('the modified macros are saved successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.updated_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.updated_host.name).click();
  waitForHostForm();
  cy.checkMacrosFieldsValues(
    hostMacros.updated_host.normalMacro,
    hostMacros.updated_host.passMacro
  );
});

Given('a configured host with macros', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.updated_host.name})`
  );
});

When('the non-admin user deletes the macros of the configured host', () => {
  cy.getIframeBody().contains('a', hostMacros.updated_host.name).click();
  waitForHostForm();
  // Remove the normal macro
  getFormBody().find('#macro_remove_current').eq(0).click();
  // Remove tha password macro
  getFormBody().find('#macro_remove_current').eq(0).click();
});

Then('the macros are deleted successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.updated_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.updated_host.name).click();
  waitForHostForm();
  // Check the non-existence of the Macros
  getFormBody()
    .contains(hostMacros.updated_host.normalMacro.name)
    .should('not.exist');
  getFormBody()
    .contains(hostMacros.updated_host.passMacro.name)
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
      `${hostMacros.updated_host.normalMacro.name}`
    );
    expect(output).not.to.match(regexNormal);
    const regexPassword = new RegExp(
      `${hostMacros.updated_host.passMacro.name}`
    );
    expect(output).not.to.match(regexPassword);
  });
});

Given(
  'a non-admin user is on the "Configuration > Hosts > Templates" page',
  () => {
    cy.visitHostTemplatesListing(0);
  }
);

Given(
  'a Host Template {string} exists with defined normal and password macros',
  (name: string) => {
    clickToAddHost();
    cy.fillHostBasicsInfos(name, name);
    cy.fillMacros(
      false,
      hostMacros.default_host.normalMacro,
      hostMacros.default_host.passMacro
    );
    // Save the configuration
    getFormBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
    cy.wait('@getTimeZone');
    // Wait until the host template is charged on the DOM
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
  }
);

Given(
  'a pre-configured Host using {string} as its parent template',
  (parent: string) => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${hostMacros.default_host.name})`
    );
    cy.getIframeBody().contains('a', hostMacros.default_host.name).click();
    waitForHostForm();
    // Add the host template to the host
    getFormBody().find('#template_add').click();
    getFormBody().find('span[role="presentation"]').eq(1).click();
    getFormBody().find(`div[title="${parent}"]`).click();
    // Save the configuration
    getFormBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
    cy.wait('@getTimeZone');
    // Wait until the host is charged on the DOM page
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${hostMacros.default_host.name})`
    );
  }
);

Then(
  'the macros should be stored in the Host Template configuration file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      false,
      hostMacros.default_host.normalMacro,
      hostMacros.default_host.passMacro
    );
  }
);

Then(
  'the Host configuration file {string} should not contain the inherited macros',
  (file: string) => {
    cy.execInContainer({
      command: `cat ${file}`,
      name: 'web'
    }).then((result) => {
      expect(result.exitCode).to.eq(0);
      const output = result.output;
      const regexNormal = new RegExp(
        `${hostMacros.default_host.normalMacro.name}`
      );
      expect(output).not.to.match(regexNormal);
      const regexPassword = new RegExp(
        `${hostMacros.default_host.passMacro.name}`
      );
      expect(output).not.to.match(regexPassword);
    });
  }
);

When(
  'the non-admin user creates a new Host Template {string} with {string} as its parent',
  (child: string, _parent: string) => {
    clickToAddHost();
    cy.fillHostBasicsInfos(child, child);
    getFormBody().find('#template_add').click();
    getFormBody().find('span[role="presentation"]').eq(1).click();
    getFormBody().find('div[title="HT-A"]').click();
  }
);

When(
  'the non-admin user changes the value of the normal macro inherited from Host Template {string}',
  (_name: string) => {
    // Check first that the inherited macros are visible
    [0, 1].forEach((index) => {
      getFormBody().find(`#macroInput_${index}`).should('be.visible');
    });
    // Check that the inherited macros are highlighted in orange
    [0, 1].forEach((index) => {
      getFormBody()
        .find(`#macroInput_${index}`)
        .should('have.attr', 'style')
        .and('include', 'var(--custom-macros-template-background-color)');
    });
    // Now change the normal macro value
    getFormBody()
      .find('#macroValue_0')
      .clear()
      .type(`${hostMacros.updated_host.normalMacro.value}`);
  }
);

Then(
  'the normal macro value in {string} should be the modified value',
  (name: string) => {
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    waitForHostForm();
    getFormBody()
      .find('#macroValue_0')
      .should('have.value', `${hostMacros.updated_host.normalMacro.value}`);
  }
);

Then('the normal macro should not be highlighted in orange', () => {
  getFormBody().find('#macroInput_0').should('not.have.attr', 'style');
});

Then('the password macro should still be highlighted in orange', () => {
  getFormBody()
    .find('#macroInput_1')
    .should('have.attr', 'style')
    .and('include', 'var(--custom-macros-template-background-color)');
});

Then('the export configuration is done with success', () => {
  cy.exportConfig();
  cy.wait('@exportConf').its('response.statusCode').should('eq', 204);
});

Given(
  'a pre-configured Host Template {string} that contains defined macros',
  (name: string) => {
    cy.waitForElementInIframe('#main-content', `a:contains("${name}")`);
  }
);

When(
  'the user creates a new Host {string} using {string} as its parent template',
  (host: string, hostTemplate: string) => {
    cy.visitHostsListingPage(0);
    clickToAddHost();
    cy.fillHostBasicsInfos(host, host);
    getFormBody()
      .find('input[name="host_address"]')
      .clear()
      .type(hostMacros.default_host.address);
    getFormBody()
      .contains('.cf-field', 'ACL Resource Groups')
      .find('.select2-selection')
      .click();
    getFormBody().contains('div', 'user-ACLGROUP').click();
    getFormBody().find('#template_add').click();
    getFormBody().find('span[role="presentation"]').eq(1).click();
    getFormBody().find(`div[title="${hostTemplate}"]`).click();
  }
);

Then(
  'the macro values in Host Template {string} should remain unchanged',
  (name: string) => {
    cy.visitHostTemplatesListing(0);
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    waitForHostForm();
    getFormBody()
      .find('#macroValue_0')
      .should('have.value', `${hostMacros.default_host.normalMacro.value}`);
  }
);

Given(
  'a pre-configured Host using a host template with defined macros as its parent template',
  () => {
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains(${hostMacros.default_host.name})`
    );
  }
);

When('the normal macro value in the host should be the modified value', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.default_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.default_host.name).click();
  waitForHostForm();
  getFormBody()
    .find('#macroValue_0')
    .should('have.value', `${hostMacros.updated_host.normalMacro.value}`);
});

Then(
  'the new value of the inherited normal macro is exported to the file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      true,
      hostMacros.updated_host.normalMacro,
      hostMacros.updated_host.passMacro
    );
  }
);

Then(
  'the old values of macros are exported to the host template file {string}',
  (file: string) => {
    cy.checkMacrosFromTheExportFile(
      file,
      false,
      hostMacros.default_host.normalMacro,
      hostMacros.default_host.passMacro
    );
  }
);
