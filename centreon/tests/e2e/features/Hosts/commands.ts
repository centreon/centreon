import { PAGES } from 'fixtures/shared/constants/pages';

Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.getIframeBody(iframeSelector).then(($iframeBody) => {
          const element = $iframeBody.find(elementSelector);

          return element.length > 0 && element.is(':visible');
        }),
      {
        errorMsg: `Element ${elementSelector} not found in iframe ${iframeSelector} after waiting`,
        interval: 1000,
        timeout: 60000
      }
    );
  }
);

Cypress.Commands.add('checkLegacyRadioButton', (label: string) => {
  cy.getIframeBody()
    .contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody()
        .find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');
    });
});

Cypress.Commands.add(
  'updateHostGroupViaApi',
  (body: HostGroup, hostGroupName: string) => {
    const query = `SELECT h.hg_id from hostgroup as h WHERE h.hg_name='${hostGroupName}'`;
    cy.requestOnDatabase({
      database: 'centreon',
      query
    }).then(([rows]) => {
      cy.request({
        body: body,
        method: 'PUT',
        url: `/centreon/api/beta/configuration/hosts/groups/${rows[0].hg_id}`
      }).then((response) => {
        expect(response.status).to.eq(204);
      });
    });
  }
);

// Dependency forms are rendered in the side panel — a second iframe nested in
// #main-content — so every field lookup goes through cy.getSidePanelBody().
// select2 fields are addressed by their visible label rather than by index.

Cypress.Commands.add('addHostDependency', (body: HostDependency) => {
  cy.getSidePanelBody()
    .find('input[name="dep_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(body.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getSidePanelBody().find('label[for="eDown"]').click({ force: true });
  cy.getSidePanelBody().find('label[for="nPending"]').click({ force: true });
  cy.pickSidePanelOption('Host Names', body.hostNames[0]);
  cy.pickSidePanelOption('Dependent Host Names', body.dependentHostNames[0]);
  cy.pickSidePanelOption('Dependent Services', body.dependentServices[0]);
  cy.getSidePanelBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

Cypress.Commands.add('updateHostDependency', (body: HostDependency) => {
  cy.getSidePanelBody()
    .find('input[name="dep_name"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(body.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .clear()
    .type(body.description);
  cy.getSidePanelBody().find('label[for="eDown"]').click({ force: true });
  cy.getSidePanelBody().find('label[for="eUp"]').click({ force: true });
  cy.getSidePanelBody().find('label[for="nPending"]').click({ force: true });
  cy.getSidePanelBody().find('label[for="nDown"]').click({ force: true });
  cy.clearSidePanelSelection('Host Names');
  cy.pickSidePanelOption('Host Names', body.hostNames[0]);
  cy.pickSidePanelOption('Host Names', body.hostNames[1]);
  cy.clearSidePanelSelection('Dependent Host Names');
  cy.pickSidePanelOption('Dependent Host Names', body.dependentHostNames[0]);
  cy.clearSidePanelSelection('Dependent Services');
  cy.pickSidePanelOption(
    'Dependent Services',
    `host2 - ${body.dependentServices[0]}`
  );
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .clear()
    .type(body.comment);
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

Cypress.Commands.add('addHostGroupDependency', (body: HostGroupDependency) => {
  cy.getSidePanelBody()
    .find('input[name="dep_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(body.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getSidePanelBody().find('label[for="eDown"]').click({ force: true });
  cy.getSidePanelBody().find('label[for="nPending"]').click({ force: true });
  cy.pickSidePanelOption('Host Groups Name', body.hostGroupsNames[0]);
  cy.pickSidePanelOption(
    'Dependent Host Groups Name',
    body.dependentHostGroupsNames[0]
  );
  cy.getSidePanelBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

Cypress.Commands.add(
  'updateHostGroupDependency',
  (body: HostGroupDependency) => {
    cy.getSidePanelBody()
      .find('input[name="dep_name"]', { timeout: 20_000 })
      .should('be.visible')
      .clear()
      .type(body.name);
    cy.getSidePanelBody()
      .find('input[name="dep_description"]')
      .clear()
      .type(body.description);
    cy.getSidePanelBody().find('label[for="eDown"]').click({ force: true });
    cy.getSidePanelBody().find('label[for="eUp"]').click({ force: true });
    cy.getSidePanelBody().find('label[for="nPending"]').click({ force: true });
    cy.getSidePanelBody().find('label[for="nDown"]').click({ force: true });
    cy.clearSidePanelSelection('Host Groups Name');
    cy.pickSidePanelOption('Host Groups Name', body.hostGroupsNames[0]);
    cy.pickSidePanelOption('Host Groups Name', body.hostGroupsNames[1]);
    cy.clearSidePanelSelection('Dependent Host Groups Name');
    cy.pickSidePanelOption(
      'Dependent Host Groups Name',
      body.dependentHostGroupsNames[0]
    );
    cy.getSidePanelBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getSidePanelBody()
      .find('input.btc.bt_success[name^="submit"]')
      .first()
      .click();
  }
);

Cypress.Commands.add('lockHostTemplateWithSql', (name: string) => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `UPDATE host SET host_locked = 1 WHERE host_name = "${name}"`
  }).then(([rows]) => {
    if (rows.length === 0) {
      throw new Error(`Host template not found for template name ${name}`);
    }
  });
});

Cypress.Commands.add('visitHostsListingPage', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
});

interface HostGroup {
  name: string;
  alias: string;
  iconId: number;
  geoCoords: string;
  comment: string;
  isActivated: boolean;
}

interface HostDependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnDown: number;
  executionFailsOnUnreachable: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnDown: number;
  notificationFailsOnUnreachable: number;
  notificationFailsOnPending: number;
  hostNames: Array<string>;
  dependentHostNames: Array<string>;
  dependentServices: Array<string>;
  comment: string;
}

interface HostGroupDependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnDown: number;
  executionFailsOnUnreachable: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnDown: number;
  notificationFailsOnUnreachable: number;
  notificationFailsOnPending: number;
  hostGroupsNames: Array<string>;
  dependentHostGroupsNames: Array<string>;
  comment: string;
}

// ---------------------------------------------------------------------------
// Host categories commands
// ---------------------------------------------------------------------------

Cypress.Commands.add('openHostCategoriesListing', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');

  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Cypress.Commands.add('getHostCategorySidePanelBody', () => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openHostCategoryForm', (name: string) => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();

  cy.getHostCategorySidePanelBody()
    .find('input[name="hc_name"]', { timeout: 20000 })
    .should('be.visible');
});

Cypress.Commands.add(
  'selectHostCategoryFieldOption',
  (label: string, option: string) => {
    cy.getHostCategorySidePanelBody()
      .contains('.cf-field', label)
      .find('.select2-selection')
      .click({ force: true });

    cy.getHostCategorySidePanelBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(option)
      .click({ force: true });
  }
);

Cypress.Commands.add('createHostCategory', (body: Record<string, unknown>) => {
  cy.request({
    body,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/categories'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
});

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      checkLegacyRadioButton: (label: string) => Cypress.Chainable;
      updateHostGroupViaApi: (
        body: HostGroup,
        name: string
      ) => Cypress.Chainable;
      addHostDependency: (body: HostDependency) => Cypress.Chainable;
      updateHostDependency: (body: HostDependency) => Cypress.Chainable;
      addHostGroupDependency: (body: HostGroupDependency) => Cypress.Chainable;
      updateHostGroupDependency: (
        body: HostGroupDependency
      ) => Cypress.Chainable;
      lockHostTemplateWithSql: (name: string) => Cypress.Chainable;
      visitHostsListingPage: () => Cypress.Chainable;
      openHostCategoriesListing(): Chainable<void>;
      getHostCategorySidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openHostCategoryForm(name: string): Chainable<void>;
      selectHostCategoryFieldOption(
        label: string,
        option: string
      ): Chainable<void>;
      createHostCategory(body: Record<string, unknown>): Chainable<void>;
    }
  }
}
