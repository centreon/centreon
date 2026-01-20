Cypress.Commands.add('addEscalation', (body: Escalation) => {
  cy.waitForElementInIframe('#main-content', 'input[name="esc_name"]');
  cy.getIframeBody().find('input[name="esc_name"]').type(body.name);
  cy.getIframeBody().find('input[name="esc_alias"]').type(body.alias);
  cy.getIframeBody()
    .find('input[name="first_notification"]')
    .type(body.firstNotification);
  cy.getIframeBody()
    .find('input[name="last_notification"]')
    .type(body.lastNotification);
  cy.getIframeBody()
    .find('input[name="notification_interval"]')
    .type(body.notificationInterval);
  cy.getIframeBody()
    .find('span[id="select2-escalation_period-container"]')
    .click();
  cy.waitForElementInIframe(
    '#main-content',
    `div[title=${body.escalationPeriod}]`
  );
  cy.getIframeBody().find(`div[title=${body.escalationPeriod}]`).click();
  cy.getIframeBody()
    .find('input[name="escalation_options1[d]"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[name="escalation_options2[u]"]')
    .click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.contactGroups}"]`).click();
  cy.getIframeBody().find('textarea[name="esc_comment"]').type(body.comment);

  cy.getIframeBody().contains('a', 'Impacted Resources').click();
  cy.get('body').click(0, 0);
  cy.getIframeBody()
    .find('input[name="host_inheritance_to_services"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.hosts}"]`).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.services}"]`).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(3)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.hostGroups}"]`).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(4)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.serviceGroups}"]`).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(5)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.metaServices}"]`).click();
});

Cypress.Commands.add('updateEscalation', (body: Escalation) => {
  cy.waitForElementInIframe('#main-content', 'input[name="esc_name"]');
  cy.getIframeBody().find('input[name="esc_name"]').clear().type(body.name);
  cy.getIframeBody().find('input[name="esc_alias"]').clear().type(body.alias);
  cy.getIframeBody()
    .find('input[name="first_notification"]')
    .clear()
    .type(body.firstNotification);
  cy.getIframeBody()
    .find('input[name="last_notification"]')
    .clear()
    .type(body.lastNotification);
  cy.getIframeBody()
    .find('input[name="notification_interval"]')
    .clear()
    .type(body.notificationInterval);
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody()
    .find('span[id="select2-escalation_period-container"]')
    .click();
  cy.waitForElementInIframe(
    '#main-content',
    `div[title=${body.escalationPeriod}]`
  );
  cy.getIframeBody().find(`div[title=${body.escalationPeriod}]`).click();
  cy.getIframeBody()
    .find('input[name="escalation_options1[d]"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[name="escalation_options1[r]"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[name="escalation_options2[u]"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[name="escalation_options2[c]"]')
    .click({ force: true });
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.contactGroups}"]`).click();
  cy.getIframeBody()
    .find('textarea[name="esc_comment"]')
    .clear()
    .type(body.comment);

  cy.getIframeBody().contains('a', 'Impacted Resources').click();
  cy.get('body').click(0, 0);
  cy.getIframeBody()
    .find('input[name="host_inheritance_to_services"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('input[name="hostgroup_inheritance_to_services"]')
    .click({ force: true });
  cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.hosts}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(3).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.services}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(4).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(3)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.hostGroups}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(5).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(4)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.serviceGroups}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(6).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(5)
    .click({ force: true });
  cy.getIframeBody().find(`div[title="${body.metaServices}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add(
  'checkValuesOfEscalation',
  (name: string, body: Escalation) => {
    cy.waitForElementInIframe('#main-content', `a:contains("${name}")`);
    cy.getIframeBody().contains(name).click();
    cy.waitForElementInIframe('#main-content', 'input[name="esc_name"]');
    cy.getIframeBody()
      .find('input[name="esc_name"]')
      .should('have.value', name);
    cy.getIframeBody()
      .find('input[name="esc_alias"]')
      .should('have.value', body.alias);
    cy.getIframeBody()
      .find('input[name="first_notification"]')
      .should('have.value', body.firstNotification);
    cy.getIframeBody()
      .find('input[name="last_notification"]')
      .should('have.value', body.lastNotification);
    cy.getIframeBody()
      .find('input[name="notification_interval"]')
      .should('have.value', body.notificationInterval);
    cy.getIframeBody()
      .find('input[name="escalation_options1[r]"]')
      .should('be.checked');
    cy.getIframeBody()
      .find('input[name="escalation_options2[c]"]')
      .should('be.checked');
    cy.getIframeBody()
      .find('#escalation_period')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.escalationPeriod);
    cy.getIframeBody()
      .find('#esc_cgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.contactGroups);
    cy.getIframeBody()
      .find('textarea[name="esc_comment"]')
      .should('have.value', body.comment);

    cy.getIframeBody().contains('a', 'Impacted Resources').click();
    cy.get('body').click(0, 0);
    cy.getIframeBody()
      .find('input[name="host_inheritance_to_services"]')
      .should('not.be.checked');
    cy.getIframeBody()
      .find('#esc_hosts')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.hosts);
    cy.getIframeBody()
      .find('#esc_hServices')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.services);
    cy.getIframeBody()
      .find('input[name="hostgroup_inheritance_to_services"]')
      .should('be.checked');
    cy.getIframeBody()
      .find('#esc_hgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.hostGroups);
    cy.getIframeBody()
      .find('#esc_sgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.serviceGroups);
    cy.getIframeBody()
      .find('#esc_metas')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.metaServices);
  }
);

interface Escalation {
  name: string;
  alias: string;
  firstNotification: string;
  lastNotification: string;
  notificationInterval: string;
  escalationPeriod: string;
  contactGroups: string;
  comment: string;
  hosts: string;
  hostInheritanceToServices: number;
  hostGroups: string;
  hostGroupInheritanceToServices: number;
  services: string;
  serviceGroups: string;
  metaServices: string;
}

declare global {
  // biome-ignore lint/style/noNamespace: <explanation>
  namespace Cypress {
    interface Chainable {
      addEscalation: (body: Escalation) => Cypress.Chainable;
      updateEscalation: (body: Escalation) => Cypress.Chainable;
      checkValuesOfEscalation: (
        name: string,
        body: Escalation
      ) => Cypress.Chainable;
    }
  }
}

export {};
