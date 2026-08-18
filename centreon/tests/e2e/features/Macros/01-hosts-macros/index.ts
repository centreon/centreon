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

/**
 * A multi-select keeps its dropdown open after a pick, and its overlay swallows
 * the next click — which is the one meant for the button underneath. Toggle the
 * field closed, the way a user has to, before moving on.
 */
const pickAclResourceGroup = (): void => {
  getFormBody()
    .contains('.cf-field', 'ACL Resource Groups')
    .find('.select2-selection')
    .click();
  getFormBody().contains('div', 'user-ACLGROUP').click();
  getFormBody()
    .contains('.cf-field', 'ACL Resource Groups')
    .find('.select2-selection')
    .click();
  getFormBody().find('.select2-container--open').should('not.exist');
};

/**
 * Open a row's form in the side panel. Clicked through the name column, and
 * retried while the panel is still closed: the listing auto-refreshes every 15s,
 * so the anchor can be detached from under the click, and a click that lands on
 * nothing is silent — the panel frame just stays on about:blank, which surfaces
 * later as an empty body rather than as a failed click.
 */
/**
 * The page iframe's document, read from a requeryable subject. Reading state off
 * cy.getIframeBody() instead means chaining .find() on a wrapped body, which the
 * listing detaches on every refetch — including the one closePanel triggers.
 */
const pageDocument = (): Cypress.Chainable<Document> =>
  cy.get('#main-content', { timeout: 60_000 }).then(($frame) => {
    const doc = ($frame[0] as HTMLIFrameElement).contentDocument;
    expect(doc, 'page iframe document').to.not.be.null;

    return doc as Document;
  });

const isPanelOpen = (doc: Document): boolean =>
  doc.querySelector('#cfSidePanel.open') !== null;

/**
 * Open a row's form in the side panel.
 *
 * Closes a panel the previous step left open first: reading the form of whichever
 * object was still in it would answer on the values the test typed a moment
 * earlier. Closing is driven through the page's own cfClosePanel rather than a
 * click, so nothing is chained off a body that the ensuing refetch detaches.
 *
 * The click is then retried while the panel is still closed: the listing
 * auto-refreshes every 15s, so the anchor can be detached from under it, and a
 * click that lands on nothing is silent.
 */
const openRowForm = (name: string): void => {
  pageDocument().then((doc) => {
    if (!isPanelOpen(doc)) {
      return;
    }
    (doc.defaultView as unknown as { cfClosePanel: () => void }).cfClosePanel();
  });
  pageDocument().should((doc) => {
    expect(isPanelOpen(doc), 'panel closed before reopening').to.be.false;
  });

  // The close refetches the listing; wait for its rows before clicking one.
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"]')
    .should('have.length.greaterThan', 0);

  const clickRowWhilePanelClosed = (): void => {
    pageDocument().then((doc) => {
      if (isPanelOpen(doc)) {
        return;
      }
      cy.getIframeBody()
        .find('#clTableBody tr td:nth-child(2)')
        .contains('a', name)
        .click();
    });
  };

  clickRowWhilePanelClosed();
  clickRowWhilePanelClosed();
  pageDocument().should((doc) => {
    expect(isPanelOpen(doc), 'panel opened').to.be.true;
  });
  waitForHostForm();
};

/**
 * Names currently held by the macro rows of the open panel, read inside the
 * assertion callback so each retry sees the live DOM. Chaining .find() off the
 * panel body instead dies the moment sheepIt re-renders the rows.
 */
const expectPanelMacroNames = (
  assertNames: (names: Array<string>) => void
): void => {
  pageDocument().should((doc) => {
    const frame = doc.querySelector('#cfSidePanelFrame') as HTMLIFrameElement;
    const inputs = Array.from(
      frame.contentDocument?.querySelectorAll(
        '#macro input[id^="macroInput"]'
      ) ?? []
    );
    assertNames(inputs.map((el) => (el as HTMLInputElement).value));
  });
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
  pickAclResourceGroup();
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
  openRowForm(hostMacros.default_host.name);
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
  openRowForm(hostMacros.default_host.name);
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
  openRowForm(hostMacros.updated_host.name);
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
  openRowForm(hostMacros.updated_host.name);
  // One row at a time, waiting for the list to settle in between: removing a row
  // makes sheepIt re-render, which detaches the subject of a chained lookup.
  expectPanelMacroNames((names) => expect(names).to.have.length(2));
  getFormBody().find('#macro_remove_current').first().click();
  expectPanelMacroNames((names) => expect(names).to.have.length(1));
  getFormBody().find('#macro_remove_current').first().click();
  expectPanelMacroNames((names) => expect(names).to.have.length(0));
});

Then('the macros are deleted successfully', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains(${hostMacros.updated_host.name})`
  );
  openRowForm(hostMacros.updated_host.name);
  // Check the non-existence of the Macros
  expectPanelMacroNames((names) => {
    expect(names).to.not.include(hostMacros.updated_host.normalMacro.name);
    expect(names).to.not.include(hostMacros.updated_host.passMacro.name);
  });
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
    openRowForm(hostMacros.default_host.name);
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
    openRowForm(name);
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
    pickAclResourceGroup();
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
    openRowForm(name);
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
  openRowForm(hostMacros.default_host.name);
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
