Cypress.Commands.add(
  'waitForElementInIframe',
  (iframeSelector, elementSelector) => {
    cy.waitUntil(
      () =>
        cy.get(iframeSelector).then(($iframe) => {
          const iframeBody = $iframe[0].contentDocument.body;
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
  cy.getIframeBody().contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody().find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');
    });
});

Cypress.Commands.add('exportConfig', () => {
  cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
  cy.getByTestId({ testId: 'Export configuration' }).click();
  cy.getByTestId({ testId: 'Confirm' }).click();
});

Cypress.Commands.add('addHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .type(body.name);
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
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('updateHostDependency', (body: HostDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody()
      .find('input[name="dep_name"]')
      .clear()
      .type(body.name);
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
    cy.getIframeBody().find(`div[title="${body.hostNames[0]}"]`).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.hostNames[1]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .click();
    cy.getIframeBody().find(`div[title="${body.dependentHostNames[0]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(2)
      .type(body.dependentServices[0]);
    cy.getIframeBody().find(`div[title="host2 - ${body.dependentServices[0]}"]`).click();
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
})

interface HostGroup {
  name: string,
  alias: string,
  notes: string,
  notes_url: string,
  action_url: string,
  icon_id: number,
  icon_map_id: number,
  geo_coords: string,
  rrd: number,
  comment: string,
  is_activated: boolean
}

interface HostDependency {
  name: string,
  description: string,
  parent_relationship: number,
  execution_fails_on_ok: number,
  execution_fails_on_warning: number,
  execution_fails_on_unknown: number,
  execution_fails_on_critical: number,
  execution_fails_on_pending: number,
  execution_fails_on_none: number,
  notification_fails_on_none: number,
  notification_fails_on_ok: number,
  notification_fails_on_warning: number,
  notification_fails_on_unknown: number,
  notification_fails_on_critical: number,
  notification_fails_on_pending: number,
  hostNames: string[],
  dependentHostNames: string[],
  dependentServices: string[],
  comment: string
}

declare global {
  namespace Cypress {
    interface Chainable {
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      checkLegacyRadioButton: (label: string) => Cypress.Chainable;
      exportConfig: () => Cypress.Chainable;
      updateHostGroupViaApi: (body: HostGroup, name: string) => Cypress.Chainable;
      addHostDependency: (body: HostDependency) => Cypress.Chainable;
      updateHostDependency: (body: HostDependency) => Cypress.Chainable;
    }
  }
}

export {};
