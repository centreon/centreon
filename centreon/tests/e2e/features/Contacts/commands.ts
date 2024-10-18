  Cypress.Commands.add('addOrUpdateContact', (body: Contact) => {
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
      cy.getIframeBody()
        .find('input[id="contact_alias"]')
        .clear()
        .type(body.alias);
      cy.getIframeBody()
        .find('input[id="contact_name"]')
        .clear()
        .type(body.name);
      cy.getIframeBody()
        .find('input[id="contact_email"]')
        .clear()
        .type(body.email);
      cy.getIframeBody()
        .find('input[id="contact_pager"]')
        .clear()
        .type(body.pager);
      cy.getIframeBody().find('#contact_template_id').select(body.template);
      cy.getIframeBody().contains('label', body.isNotificationsEnabled).click();
      cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
      cy.wait('@getTimeZone');
      cy.exportConfig();
  
  });
  
  interface Contact {
    alias: string,
    name: string,
    email: string,
    pager: string,
    template: string,
    isNotificationsEnabled: string
  }
  
  declare global {
    namespace Cypress {
      interface Chainable {
        addOrUpdateContact: (body: Contact) => Cypress.Chainable;
      }
    }
  }
  
  export {};