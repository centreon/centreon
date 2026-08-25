// The escalation form is rendered in the side panel — a second iframe nested in
// #main-content — so every field lookup goes through cy.getSidePanelBody().

Cypress.Commands.add('addEscalation', (body: Escalation) => {
  cy.getSidePanelBody()
    .find('input[name="esc_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(body.name);
  cy.getSidePanelBody().find('input[name="esc_alias"]').type(body.alias);
  cy.getSidePanelBody()
    .find('input[name="first_notification"]')
    .type(body.firstNotification);
  cy.getSidePanelBody()
    .find('input[name="last_notification"]')
    .type(body.lastNotification);
  cy.getSidePanelBody()
    .find('input[name="notification_interval"]')
    .type(body.notificationInterval);
  cy.pickSidePanelOption('Escalation Period', body.escalationPeriod);
  cy.getSidePanelBody()
    .find('input[name="escalation_options1[d]"]')
    .click({ force: true });
  cy.getSidePanelBody()
    .find('input[name="escalation_options2[u]"]')
    .click({ force: true });
  cy.pickSidePanelOption('Linked Contact Groups', body.contactGroups);
  cy.getSidePanelBody().find('textarea[name="esc_comment"]').type(body.comment);

  cy.getSidePanelBody().contains('a', 'Impacted Resources').click();
  cy.getSidePanelBody()
    .find('input[name="host_inheritance_to_services"]')
    .click({ force: true });
  cy.pickSidePanelOption('Hosts', body.hosts);
  cy.pickSidePanelOption('Services by Host', body.services);
  cy.pickSidePanelOption('Host Group', body.hostGroups);
  cy.pickSidePanelOption('Service Group', body.serviceGroups);
  cy.pickSidePanelOption('Meta Service', body.metaServices);
});

Cypress.Commands.add('updateEscalation', (body: Escalation) => {
  cy.getSidePanelBody()
    .find('input[name="esc_name"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(body.name);
  cy.getSidePanelBody()
    .find('input[name="esc_alias"]')
    .clear()
    .type(body.alias);
  cy.getSidePanelBody()
    .find('input[name="first_notification"]')
    .clear()
    .type(body.firstNotification);
  cy.getSidePanelBody()
    .find('input[name="last_notification"]')
    .clear()
    .type(body.lastNotification);
  cy.getSidePanelBody()
    .find('input[name="notification_interval"]')
    .clear()
    .type(body.notificationInterval);
  cy.pickSidePanelOption('Escalation Period', body.escalationPeriod);
  cy.getSidePanelBody()
    .find('input[name="escalation_options1[d]"]')
    .click({ force: true });
  cy.getSidePanelBody()
    .find('input[name="escalation_options1[r]"]')
    .click({ force: true });
  cy.getSidePanelBody()
    .find('input[name="escalation_options2[u]"]')
    .click({ force: true });
  cy.getSidePanelBody()
    .find('input[name="escalation_options2[c]"]')
    .click({ force: true });
  cy.clearSidePanelSelection('Linked Contact Groups');
  cy.pickSidePanelOption('Linked Contact Groups', body.contactGroups);
  cy.getSidePanelBody()
    .find('textarea[name="esc_comment"]')
    .clear()
    .type(body.comment);

  cy.getSidePanelBody().contains('a', 'Impacted Resources').click();
  cy.getSidePanelBody()
    .find('input[name="host_inheritance_to_services"]')
    .click({ force: true });
  cy.getSidePanelBody()
    .find('input[name="hostgroup_inheritance_to_services"]')
    .click({ force: true });
  cy.clearSidePanelSelection('Hosts');
  cy.pickSidePanelOption('Hosts', body.hosts);
  cy.clearSidePanelSelection('Services by Host');
  cy.pickSidePanelOption('Services by Host', body.services);
  cy.clearSidePanelSelection('Host Group');
  cy.pickSidePanelOption('Host Group', body.hostGroups);
  cy.clearSidePanelSelection('Service Group');
  cy.pickSidePanelOption('Service Group', body.serviceGroups);
  cy.clearSidePanelSelection('Meta Service');
  cy.pickSidePanelOption('Meta Service', body.metaServices);

  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add(
  'checkValuesOfEscalation',
  (name: string, body: Escalation) => {
    cy.openSidePanelForm(name, 'input[name="esc_name"]');
    cy.getSidePanelBody()
      .find('input[name="esc_name"]')
      .should('have.value', name);
    cy.getSidePanelBody()
      .find('input[name="esc_alias"]')
      .should('have.value', body.alias);
    cy.getSidePanelBody()
      .find('input[name="first_notification"]')
      .should('have.value', body.firstNotification);
    cy.getSidePanelBody()
      .find('input[name="last_notification"]')
      .should('have.value', body.lastNotification);
    cy.getSidePanelBody()
      .find('input[name="notification_interval"]')
      .should('have.value', body.notificationInterval);
    cy.getSidePanelBody()
      .find('input[name="escalation_options1[r]"]')
      .should('be.checked');
    cy.getSidePanelBody()
      .find('input[name="escalation_options2[c]"]')
      .should('be.checked');
    // Single select2: the chosen value is shown in the rendered container, not
    // as a selected <option> (select2 adds its options dynamically).
    cy.getSidePanelBody()
      .find('#select2-escalation_period-container')
      .should('have.text', body.escalationPeriod);
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.contactGroups}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find('textarea[name="esc_comment"]')
      .should('have.value', body.comment);

    cy.getSidePanelBody().contains('a', 'Impacted Resources').click();
    cy.getSidePanelBody()
      .find('input[name="host_inheritance_to_services"]')
      .should('not.be.checked');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.hosts}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.services}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find('input[name="hostgroup_inheritance_to_services"]')
      .should('be.checked');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.hostGroups}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.serviceGroups}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${body.metaServices}"]`)
      .should('exist');
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
  // biome-ignore lint/style/noNamespace: false positive
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
