Cypress.Commands.add('addOrUpdateVirtualMetric', (body: VirtualMetric) => {
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="vmetric_name"]');
    cy.getIframeBody()
      .find('input[name="vmetric_name"]')
      .clear()
      .type(body.name);
    cy.getIframeBody()
      .find('span[id="select2-host_id-container"]')
      .click();
    cy.getIframeBody()
      .find(`div[title='${body.linkedHostServices}']`)
      .click();
    cy.getIframeBody()
      .find('textarea[name="rpn_function"]')
      .clear();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService')
    cy.waitUntil(
        () => {
          return cy.getIframeBody()
          .find('.select2-results')
          .find('li')
            .then(($lis) => {
              const count = $lis.length;
              if (count <= 1) {
                cy.exportConfig();
                cy.getIframeBody()
                  .find('span[title="Clear field"]')
                  .eq(1)
                  .click();
                cy.getIframeBody()
                  .find('span[id="select2-sl_list_metrics-container"]')
                  .click();
                cy.wait('@getListOfMetricsByService')
              }
              return count > 1;
            });
        },
        { interval: 10000, timeout: 600000 }
    );
    
    cy.getIframeBody()
      .find('span[title="Clear field"]')
      .eq(1)
      .click();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService')
    cy.getIframeBody()
      .find(`div[title='${body.knownMetric}']`)
      .click();  
    cy.getIframeBody()
      .find('#td_list_metrics img')
      .eq(0)
      .click();
    cy.getIframeBody()
      .find('input[name="unit_name"]')
      .clear()
      .type(body.unit); 
    cy.getIframeBody()
      .find('input[name="warn"]')
      .clear()
      .type(body.warning_threshold); 
    cy.getIframeBody()
      .find('input[name="crit"]')
      .clear()
      .type(body.critical_threshold); 
    cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(0).click();
    cy.getIframeBody()
      .find('textarea[name="comment"]')
      .clear()
      .type(body.comments);
    cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
});

Cypress.Commands.add('checkFieldsOfVM', (body: VirtualMetric) => {
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="vmetric_name"]');
  cy.getIframeBody()
    .find('input[name="vmetric_name"]')
    .should('have.value', body.name);
  cy.getIframeBody()
    .find('#select2-host_id-container')
    .should('have.attr', 'title', body.linkedHostServices);
  cy.getIframeBody()
    .find('textarea[name="rpn_function"]')
    .should('have.value', body.knownMetric);
  cy.getIframeBody()
    .find('input[name="unit_name"]')
    .should('have.value', body.unit);
  cy.getIframeBody()
    .find('input[name="warn"]')
    .should('have.value', body.warning_threshold);
  cy.getIframeBody()
    .find('input[name="crit"]')
    .should('have.value', body.critical_threshold);
  cy.getIframeBody()
    .find('textarea[name="comment"]')
    .should('have.value', body.comments);
});

interface VirtualMetric {
  name: string,
  linkedHostServices: string,
  knownMetric: string,
  unit: string,
  warning_threshold: string,
  critical_threshold: string,
  comments: string,
}

declare global {
  namespace Cypress {
    interface Chainable {
      addOrUpdateVirtualMetric: (body: VirtualMetric) => Cypress.Chainable;
      checkFieldsOfVM: (body: VirtualMetric) => Cypress.Chainable;
    }
  }
}

export {};