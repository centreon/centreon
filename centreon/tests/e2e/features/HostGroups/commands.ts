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
    // If body contains "geo_coords_after_truncate" don't add it to the body
    const filteredBody = { ...body };
    // biome-ignore lint/performance/noDelete: <explanation>
    // biome-ignore lint/suspicious/noExplicitAny: <explanation>
    delete (filteredBody as any).geo_coords_after_truncate;
    cy.requestOnDatabase({
      database: 'centreon',
      query
    }).then(([rows]) => {
      cy.request({
        body: filteredBody,
        method: 'PUT',
        url: `/centreon/api/beta/configuration/hosts/groups/${rows[0].hg_id}`
      }).then((response) => {
        expect(response.status).to.eq(204);
      });
    });
  }
);

Cypress.Commands.add('exportConfig', () => {
  cy.getByTestId({ testId: 'ExpandMoreIcon' }).eq(0).click();
  cy.getByTestId({ testId: 'Export configuration' }).click();
  cy.getByTestId({ testId: 'Confirm' }).click();
});

interface HostGroup {
  name: string;
  alias: string;
  notes: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  notes_url: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  action_url: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  icon_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  icon_map_id: number;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  geo_coords: string;
  rrd: number;
  comment: string;
  // biome-ignore lint/style/useNamingConvention: <explanation>
  is_activated: boolean;
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
      exportConfig: () => Cypress.Chainable;
    }
  }
}

export {};
