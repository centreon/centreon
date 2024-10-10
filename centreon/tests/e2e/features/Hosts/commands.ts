/* eslint-disable no-plusplus */
/* eslint-disable prefer-destructuring */
/* eslint-disable @typescript-eslint/method-signature-style */
/* eslint-disable @typescript-eslint/explicit-function-return-type */
/* eslint-disable @typescript-eslint/no-shadow */
/* eslint-disable @typescript-eslint/no-namespace */

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

Cypress.Commands.add('isEnableOrDisableResourceOnHostGroupChecked', (label: string) => {
  cy.getIframeBody().contains('label', label)
    .should('exist')
    .then(($label) => {
      const radioId = $label.attr('for');
      cy.getIframeBody().find(`input[type="radio"][id="${radioId}"]`)
        .should('be.checked');;
    });
});

Cypress.Commands.add('updateHostGroupViaApi', (body: HostGroup, hostGroup_name: string) => {
  let query =
    `SELECT h.hg_id  from hostgroup as h WHERE h.hg_name='${hostGroup_name}'`;
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

});

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

declare global {
  namespace Cypress {
    interface Chainable {
      waitForElementInIframe: (
        iframeSelector: string,
        elementSelector: string
      ) => Cypress.Chainable;
      isEnableOrDisableResourceOnHostGroupChecked: (label: string) => Cypress.Chainable;
      updateHostGroupViaApi: (body: HostGroup, name: string) => Cypress.Chainable;
    }
  }
}

export {};