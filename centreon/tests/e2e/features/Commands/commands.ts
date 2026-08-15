interface Cmd {
  name: string;
  type: string;
  globalMacros: string;
  standardMacros: string;
  installedPlugins: string;
  isShell: boolean;
  connectorName: number;
  comments: string;
}

interface Ctr {
  name: string;
  description: string;
  commandLine: string;
  usedByCommand: string;
  isEnabled: number;
}

Cypress.Commands.add(
  'fillCommandLine',
  (index: number, request: string, value: string, label: string) => {
    cy.getByLabel({ label: 'Open', tag: 'button' }).eq(index).click();
    cy.wait(`@${request}`);
    cy.contains('.MuiAutocomplete-option p', value).click();
    cy.getByTestId({ tag: 'button', testId: label }).click();
  }
);

Cypress.Commands.add('searchForCommandsByName', (name: string) => {
  cy.get('#searchbar').clear().type(name);
  cy.wait('@getCommandsList');
  cy.wait(2000);
  cy.contains('p', name).should('be.visible');
});

Cypress.Commands.add('addOrUpdateCommands', (body: Cmd) => {
  // Type a value on the "Command Name" input
  cy.get('#Name').clear().type(body.name);
  // Chose a "Command Type"
  cy.get(`input[value="${body.type}"]`).click();
  // Type a value on the "Command Line" textarea
  cy.get('#Commandline').clear();
  cy.fillCommandLine(
    0,
    'getGlobalMacros',
    body.globalMacros,
    'Insert global marco'
  );
  cy.fillCommandLine(
    1,
    'getPlugins',
    body.installedPlugins,
    'Insert installed plugin'
  );
  cy.fillCommandLine(
    2,
    'getStandardMacros',
    body.standardMacros,
    'Insert standard marco'
  );
  // Enable/ Disable shell
  cy.get('span[data-testid="enable-shell-syntax"]').then(($el) => {
    if (body.isShell === true && $el.length) {
      cy.wrap($el).click();
    }
  });
  // Chose a connector
  cy.getByLabel({ label: 'Open', tag: 'button' }).eq(3).click();
  cy.wait('@getConnectors');
  cy.contains(body.connectorName).click();
  // Write a comment
  cy.get('#Comments').clear().type(body.comments);
});

Cypress.Commands.add('checkValuesOfCommands', (name: string, body: Cmd) => {
  // Check that the "Command Name" input contains right value
  cy.get('#Name').invoke('val').should('include', name);
  // Check that the "Command Type" input contains right value
  cy.get(`input[type="radio"][value="${body.type}"]`).should('be.checked');

  // Check that the "Command Line" input contains right value
  [body.globalMacros, body.standardMacros, body.installedPlugins].forEach(
    (value) => {
      cy.get('#Commandline').invoke('val').should('include', value);
    }
  );
  // Check that the "Enable Shell" checkbox contains right value
  cy.get('span[data-testid="enable-shell-syntax"] input[type="checkbox"]').then(
    ($checkbox) => {
      if (body.isShell === true) {
        cy.wrap($checkbox).should('be.checked');
      } else {
        cy.wrap($checkbox).should('not.be.checked');
      }
    }
  );
  // Check that the "Connectors" contains right value
  cy.get('#Selectanoptimizationconnector').should(
    'have.value',
    body.connectorName
  );
  // Check that the "Comments" contains right value
  cy.get('#Comments').should('have.value', body.comments);
});

Cypress.Commands.add(
  'addCommandToResource',
  (index: number, command: string) => {
    cy.getIframeBody().find('span[title="Clear field"]').eq(index).click();
    // Click on the check command field in the form
    cy.getIframeBody().find('span[title="Check Command"]').click();
    // Chose a check command
    cy.getIframeBody().find(`div[title="${command}"]`).click();
  }
);

/**
 * Body of the connector form, which the modernized listing opens in a side
 * panel: an iframe nested inside #main-content, out of reach of getIframeBody().
 */
Cypress.Commands.add('getConnectorSidePanelBody', () => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

/**
 * Pick the command the connector is used by. The redesigned select2 hides its
 * inline search input behind a "Select all" header, so the selection container
 * is the reliable target.
 */
const selectUsedByCommand = (command: string): void => {
  cy.getConnectorSidePanelBody()
    .contains('.cf-field', 'Used by command')
    .find('.select2-selection')
    .click({ force: true });

  cy.getConnectorSidePanelBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(command)
    .click({ force: true });
};

/**
 * Activation is driven by a cosmetic toggle; the radio group it mirrors is
 * hidden, so the real input sits behind the slider, hence the forced click.
 */
const setConnectorStatus = (isEnabled: number): void => {
  cy.getConnectorSidePanelBody()
    .find('#cf-connector-status-toggle')
    .then(($toggle) => {
      if ($toggle.prop('checked') !== (isEnabled === 1)) {
        cy.wrap($toggle).click({ force: true });
      }
    });
};

Cypress.Commands.add('addConnectors', (body: Ctr) => {
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(body.name);
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_description"]')
    .type(body.description);
  cy.getConnectorSidePanelBody()
    .find('textarea[id="command_line"]')
    .type(body.commandLine);
  selectUsedByCommand(body.usedByCommand);
  setConnectorStatus(body.isEnabled);
});

Cypress.Commands.add('updateConnectors', (body: Ctr) => {
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_name"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(body.name);
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_description"]')
    .clear()
    .type(body.description);
  cy.getConnectorSidePanelBody()
    .find('textarea[id="command_line"]')
    .clear()
    .type(body.commandLine);
  cy.getConnectorSidePanelBody()
    .find('span[title="Clear field"]')
    .click({ force: true });
  selectUsedByCommand(body.usedByCommand);
  setConnectorStatus(body.isEnabled);
});

Cypress.Commands.add('checkValuesOfConnectors', (name: string, body: Ctr) => {
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_name"]', { timeout: 20_000 })
    .should('have.value', `${name}`);
  cy.getConnectorSidePanelBody()
    .find('input[name="connector_description"]')
    .should('have.value', body.description);
  cy.getConnectorSidePanelBody()
    .find('textarea[id="command_line"]')
    .should('have.value', body.commandLine);
  cy.getConnectorSidePanelBody()
    .find('select[id="command_id"]')
    .then(($val) => {
      // A duplicated connector carries no command: the copy drops the relation.
      if (name.endsWith('_1')) {
        cy.wrap($val).should('have.text', '');
      } else {
        cy.wrap($val).should('have.text', body.usedByCommand);
      }
    });
  // Assert the visible control rather than the hidden radio group it mirrors.
  cy.getConnectorSidePanelBody()
    .find('#cf-connector-status-toggle')
    .should(body.isEnabled === 1 ? 'be.checked' : 'not.be.checked');
});

declare global {
  // biome-ignore lint/style/noNamespace: Need it for Cypress types
  namespace Cypress {
    interface Chainable {
      fillCommandLine: (
        index: number,
        request: string,
        value: string,
        label: string
      ) => Cypress.Chainable;
      searchForCommandsByName: (name: string) => Cypress.Chainable;
      addOrUpdateCommands: (body: Cmd) => Cypress.Chainable;
      checkValuesOfCommands: (name: string, body: Cmd) => Cypress.Chainable;
      addCommandToResource: (
        index: number,
        command: string
      ) => Cypress.Chainable;
      addConnectors: (body: Ctr) => Cypress.Chainable;
      updateConnectors: (body: Ctr) => Cypress.Chainable;
      checkValuesOfConnectors: (name: string, body: Ctr) => Cypress.Chainable;
      getConnectorSidePanelBody: () => Cypress.Chainable<JQuery<HTMLElement>>;
    }
  }
}

export {};
