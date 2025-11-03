import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import hostMacros from '../../../fixtures/macros/hosts.json';

const clickToAddHost = () => {
  cy.waitForElementInIframe('#main-content', 'a:contains("Add")');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
};

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
  cy.getIframeBody()
    .find('input[name="host_address"]')
    .clear()
    .type(hostMacros.default_host.address);
  cy.getIframeBody().find('input[placeholder="ACL Resource Groups"]').click();
  cy.getIframeBody().contains('div', 'user-ACLGROUP').click();
});

When('the non-admin user adds one normal macro and one password macro', () => {
  cy.fillMacros(
    false,
    hostMacros.default_host.normalMacro,
    hostMacros.default_host.passMacro
  );
});

When('the non-admin user clicks the "Save" button', () => {
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('all the properties, including the macros, are successfully saved', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.default_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.default_host.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', hostMacros.default_host.name);
  cy.getIframeBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostMacros.default_host.alias);
  cy.getIframeBody()
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
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
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
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
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
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  // Remove the normal macro
  cy.getIframeBody().find('#macro_remove_current').eq(0).click();
  // Remove tha password macro
  cy.getIframeBody().find('#macro_remove_current').eq(0).click();
});

Then('the macros are deleted successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.updated_host.name})`
  );
  cy.getIframeBody().contains('a', hostMacros.updated_host.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  // Check the non-existence of the Macros
  cy.getIframeBody()
    .contains(hostMacros.updated_host.normalMacro.name)
    .should('not.exist');
  cy.getIframeBody()
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
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
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
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
    // Add the host template to the host
    cy.getIframeBody().find('#template_add').click();
    cy.getIframeBody().find('span[role="presentation"]').eq(1).click();
    cy.getIframeBody().find(`div[title="${parent}"]`).click();
    // Save the configuration
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
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
    cy.getIframeBody().find('#template_add').click();
    cy.getIframeBody().find('span[role="presentation"]').eq(1).click();
    cy.getIframeBody().find('div[title="HT-A"]').click();
  }
);

When(
  'he changes the value of the normal macro inherited from Host Template {string}',
  (_name: string) => {
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
      .type(`${hostMacros.updated_host.normalMacro.value}`);
  }
);

Then(
  'the normal macro value in {string} should be the modified value',
  (name: string) => {
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
    cy.getIframeBody()
      .find('#macroValue_0')
      .should('have.value', `${hostMacros.updated_host.normalMacro.value}`);
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
    cy.getIframeBody()
      .find('input[name="host_address"]')
      .clear()
      .type(hostMacros.default_host.address);
    cy.getIframeBody().find('input[placeholder="ACL Resource Groups"]').click();
    cy.getIframeBody().contains('div', 'user-ACLGROUP').click();
    cy.getIframeBody().find('#template_add').click();
    cy.getIframeBody().find('span[role="presentation"]').eq(1).click();
    cy.getIframeBody().find(`div[title="${hostTemplate}"]`).click();
  }
);

Then(
  'the macro values in Host Template {string} should remain unchanged',
  (name: string) => {
    cy.visitHostTemplatesListing(0);
    cy.waitForElementInIframe('#main-content', `a:contains(${name})`);
    cy.getIframeBody().contains('a', name).click();
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
    cy.getIframeBody()
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
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
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
