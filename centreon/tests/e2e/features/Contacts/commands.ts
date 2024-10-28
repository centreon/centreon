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

  Cypress.Commands.add('addOrUpdateContactGroup', (body: ContactGroup) => {
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe('#main-content', 'input[name="cg_name"]');
      cy.getIframeBody()
        .find('input[name="cg_name"]')
        .clear()
        .type(body.name);
      cy.getIframeBody()
        .find('input[name="cg_alias"]')
        .clear()
        .type(body.alias);

      cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
      cy.wait('@getContacts');
      cy.getIframeBody().contains('div', body.linkedContact).click();

      cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
      cy.wait('@getACLGroups');
      cy.getIframeBody().contains('div', 'ALL').click();

      cy.getIframeBody().contains(body.status).click();

      cy.getIframeBody()
        .find('textarea[name="cg_comment"]')
        .clear()
        .type(body.comment);

      cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(1).click();
      cy.wait('@getTimeZone');
      cy.exportConfig();
  });

  Cypress.Commands.add('addOrUpdateContactTemplate', (body: ContactTemplate) => {
      cy.wait('@getTimeZone');
      cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');
      cy.getIframeBody()
        .find('input[name="contact_alias"]')
        .clear()
        .type(body.alias);
      cy.getIframeBody()
        .find('input[name="contact_name"]')
        .clear()
        .type(body.name);
      cy.getIframeBody().find('select[name="contact_template_id"]').select(body.usedCTemplate);
      cy.getIframeBody().find('select[name="default_page"]').select(body.defaultPage);
      cy.getIframeBody().contains('label',body.isNotEnabled).click();
      cy.getIframeBody().find('label[for="hDown"]').click();
      cy.getIframeBody().find('span[id="select2-timeperiod_tp_id-container"]').click();
      cy.wait('@getTimePeriods');
      cy.getIframeBody().find(`div[title="${body.timePeriod}"]`).click();
      cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
      cy.wait('@getNotCommands');
      cy.getIframeBody().find(`div[title="${body.NotCommands}"]`).click();
      cy.getIframeBody().find('label[for="sWarning"]').click();
      cy.getIframeBody().find('span[id="select2-timeperiod_tp_id2-container"]').click();
      cy.wait('@getTimePeriods');
      cy.getIframeBody().find(`div[title="${body.timePeriod}"]`).click();
      cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
      cy.wait('@getNotCommands');
      cy.getIframeBody().find(`div[title="${body.NotCommands}"]`).click();
      cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(1).click();
      cy.wait('@getTimeZone');
      cy.exportConfig();
  });
  
  Cypress.Commands.add('loginByDuplicatedUser', (jsonName: string) =>{
      cy.visit(`${Cypress.config().baseUrl}`)
        .fixture(`users/${jsonName}.json`)
        .then((credential) => {
          cy.getByLabel({ label: 'Alias', tag: 'input' }).type(
            `{selectAll}{backspace}${credential.login}_1`
          );
          cy.getByLabel({ label: 'Mot de passe', tag: 'input' }).type(
            `{selectAll}{backspace}${credential.password}`
          );
        })
        .getByLabel({ label: 'Connect', tag: 'button' })
        .click();
  
      return cy.get('.MuiAlert-message').then(($snackbar) => {
        if ($snackbar.text().includes('Login succeeded')) {
          cy.wait('@getNavigationList');
          cy.get('.MuiAlert-message').should('not.be.visible');
        }
      });
    }
  );
  
  interface Contact {
    alias: string,
    name: string,
    email: string,
    pager: string,
    template: string,
    isNotificationsEnabled: string
  }
  
  interface ContactGroup {
    name: string,
    alias: string,
    linkedContact: string,
    status: string,
    comment: string,
  }

  interface ContactTemplate {
    alias: string,
    name: string,
    usedCTemplate: string,
    defaultPage: string,
    isNotEnabled: string,
    timePeriod: string,
    NotCommands: string,
  }

  declare global {
    namespace Cypress {
      interface Chainable {
        addOrUpdateContact: (body: Contact) => Cypress.Chainable;
        addOrUpdateContactGroup: (body: ContactGroup) => Cypress.Chainable;
        addOrUpdateContactTemplate: (body: ContactTemplate) => Cypress.Chainable;
        loginByDuplicatedUser: (jsonName: string) => Cypress.Chainable;
      }
    }
  }
  
  export {};