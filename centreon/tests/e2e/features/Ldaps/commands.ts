Cypress.Commands.add('addOrUpdateLdap', (body: Ldap) => {
    cy.waitForElementInIframe('#main-content', 'input[name="ar_name"]');
    cy.getIframeBody()
      .find('input[name="ar_name"]')
      .clear()
      .type(body.name);
    cy.getIframeBody()
      .find('textarea[name="ar_description"]')
      .clear()
      .type(body.desc);
    cy.fixture(`../fixtures/users/user-with-access-to-allmodules.json`)
      .then((user) => {
        cy.getIframeBody()
          .find('input[name="bind_dn"]')
          .clear()
          .type(user.login);
        cy.getIframeBody()
          .find('input[name="bind_pass"]')
          .clear()
          .type(user.password);
      });
    cy.getIframeBody()
      .find('select[id="ldap_template"]')
      .select('Posix');
    cy.getIframeBody()
      .find('input[name="user_base_search"]')
      .clear()
      .type(body.userBaseSearch);
    cy.getIframeBody()
      .find('input[name="group_base_search"]')
      .clear()
      .type(body.groupBaseSearch);
    cy.getIframeBody()
      .find('input[name="user_group"]')
      .clear()
      .type(body.userGrpAttribute);
    cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
});

interface Ldap {
  name: string,
  desc: string,
  userBaseSearch: string,
  groupBaseSearch: string,
  userGrpAttribute: string,
}

declare global {
  namespace Cypress {
    interface Chainable {
      addOrUpdateLdap: (body: Ldap) => Cypress.Chainable;
    }
  }
}

export {};