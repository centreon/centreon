/* eslint-disable @typescript-eslint/no-namespace */

Cypress.Commands.add('visitDashboards', () => {
  cy.intercept({
    method: 'GET',
    times: 1,
    url: '/centreon/api/latest/configuration/dashboards*'
  }).as('listAllDashboards');

  const dashboardsUrl = '/centreon/home/dashboards/library';
  cy.url().then((url) =>
    url.includes(dashboardsUrl)
      ? cy.visit(dashboardsUrl)
      : cy.navigateTo({ page: 'Dashboards', rootItemNumber: 0 })
  );

  cy.wait('@listAllDashboards');
});

Cypress.Commands.add('visitDashboard', (name) => {
  cy.visitDashboards();
  cy.contains(name).click();

  cy.url().should('match', /\/home\/dashboards\/library\/\d+$/);
});

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

Cypress.Commands.add('enterIframe', (iframeSelector) => {
  cy.get(iframeSelector)
    .its('0.contentDocument')
    .should('exist')
    .its('body')
    .should('not.be.undefined')
    .then(cy.wrap);
});

Cypress.Commands.add('exportConfig', () => {
  cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
  cy.getByTestId({ testId: 'Export configuration' }).click();
  cy.getByTestId({ testId: 'Confirm' }).click();
});

Cypress.Commands.add(
  'waitForElementToBeVisible',
  (selector, timeout = 70000, interval = 3000) => {
    cy.waitUntil(
      () =>
        cy.get('body').then(($body) => {
          const element = $body.find(selector);

          return element.length > 0 && element.is(':visible');
        }),
      {
        errorMsg: `The element '${selector}' is not visible`,
        interval,
        timeout
      }
    ).then((isVisible) => {
      if (!isVisible) {
        throw new Error(`The element '${selector}' is not visible`);
      }
    });
  }
);

Cypress.Commands.add('editWidget', (nameOrPosition) => {
  if (typeof nameOrPosition === 'string') {
    cy.contains('div.react-grid-item', nameOrPosition).as('widgetItem');
  } else {
    cy.get('div.react-grid-item')
      .eq(nameOrPosition - 1)
      .as('widgetItem');
  }

  cy.get('@widgetItem').within(() => {
    cy.getByTestId({ testId: 'More actions' }).should('be.visible').click();
  });

  cy.getByLabel({
    label: 'Edit widget',
    tag: 'li'
  })
    .should('exist') // do not check with "be.visible" because it can be hidden by the tooltip of "more actions" button
    .click();

  cy.contains('Widget properties').should('be.visible');
});

Cypress.Commands.add('editDashboard', (name) => {
  cy.visitDashboard(name);

  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();

  cy.url().should('match', /\/home\/dashboards\/library\/\d+\?edit=true/);

  cy.getByLabel({
    label: 'Save',
    tag: 'button'
  }).should('be.visible');
});

Cypress.Commands.add('applyAcl', () => {
  const apacheUser = Cypress.env('WEB_IMAGE_OS').includes('alma')
    ? 'apache'
    : 'www-data';

  cy.execInContainer({
    command: `su -s /bin/sh ${apacheUser} -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"`,
    name: 'web'
  });
});

declare global {
  namespace Cypress {
    interface Chainable {
      editDashboard: (name: string) => Cypress.Chainable;
      editWidget: (nameOrPosition: string | number) => Cypress.Chainable;
      enterIframe: (iframeSelector: string) => Cypress.Chainable;
      exportConfig: () => Cypress.Chainable;
      visitDashboard: (name: string) => Cypress.Chainable;
      visitDashboards: () => Cypress.Chainable;
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      waitForElementToBeVisible(
        selector: string,
        timeout?: number,
        interval?: number
      ): Cypress.Chainable;
      waitUntilForDashboardRoles: (
        accessRightsTestId: string,
        expectedElementCount: number
      ) => Cypress.Chainable;
      applyAcl: () => Cypress.Chainable;
    }
  }
}

export {};
