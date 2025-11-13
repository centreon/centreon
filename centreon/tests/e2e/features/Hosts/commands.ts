Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.get(iframeSelector).then(($iframe) => {
          const iframeBody = ($iframe[0] as HTMLIFrameElement).contentDocument
            ?.body;
          if (iframeBody) {
            const $element = Cypress.$(iframeBody).find(elementSelector);

            return $element.length > 0 && $element.is(':visible');
          }

          return false;
        }),
      {
        errorMsg: 'The element is not visible within the iframe',
        interval: 5000,
        timeout: 100000
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error('The element is not visible');
      }
    });
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

Cypress.Commands.add('addHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody().find(`div[title="${body.dependentHostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(2).click();
  cy.getIframeBody().find(`div[title="${body.dependentServices[0]}"]`).click();
  cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('updateHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .clear()
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="eUp"]').click({ force: true });

  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('label[for="nDown"]').click({ force: true });
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostNames[1]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody().find(`div[title="${body.dependentHostNames[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .type(body.dependentServices[0]);
  cy.getIframeBody()
    .find(`div[title="host2 - ${body.dependentServices[0]}"]`)
    .click();
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .clear()
    .type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('addHostGroupDependency', (body: HostGroupDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
  cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.hostGroupsNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody()
    .find(`div[title="${body.dependentHostGroupsNames[0]}"]`)
    .click();
  cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add(
  'updateHostGroupDependency',
  (body: HostGroupDependency) => {
    cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
    cy.getIframeBody()
      .find('input[name="dep_description"]')
      .clear()
      .type(body.description);
    cy.getIframeBody().find('label[for="eDown"]').click({ force: true });
    cy.getIframeBody().find('label[for="eUp"]').click({ force: true });

    cy.getIframeBody().find('label[for="nPending"]').click({ force: true });
    cy.getIframeBody().find('label[for="nDown"]').click({ force: true });
    cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.hostGroupsNames[0]}"]`).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.hostGroupsNames[1]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .type(body.dependentHostGroupsNames[0]);
    cy.getIframeBody()
      .find(`div[title="${body.dependentHostGroupsNames[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
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

Cypress.Commands.add('visitHostsListingPage', (index: number) => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: index,
    subMenu: 'Hosts'
  });
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
  hostNames: string[];
  dependentHostNames: string[];
  dependentServices: string[];
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
  hostGroupsNames: string[];
  dependentHostGroupsNames: string[];
  comment: string;
}

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
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
      visitHostsListingPage: (index: number) => Cypress.Chainable;
    }
  }
}

export {};
