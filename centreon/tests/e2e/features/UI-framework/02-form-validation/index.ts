import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { buildHostGroupsByNameQuery, type HostGroupRow } from '../common';

const draftAlias = 'rbt ui form draft';
const addFormTitle = 'Add a host group';
const editFormTitle = 'Modify a host group';

let formHostGroup = '';

beforeEach(() => {
  cy.startContainers();

  formHostGroup = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?*`
  }).as('getHostGroups');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/*`
  }).as('getHostGroup');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups`
  }).as('postHostGroup');
  cy.intercept({
    method: 'PUT',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/*`
  }).as('putHostGroup');
});

afterEach(() => {
  cy.stopContainers();
});

const hostGroupsNamed = (
  name: string
): Cypress.Chainable<Array<HostGroupRow>> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildHostGroupsByNameQuery([name])
    })
    .then(([rows]) => cy.wrap(rows as Array<HostGroupRow>, { log: false }));

const nameInput = (): Cypress.Chainable => cy.get('input[data-testid="Name"]');
const aliasInput = (): Cypress.Chainable =>
  cy.get('input[data-testid="Alias"]');
const saveButton = (): Cypress.Chainable =>
  cy.get('button[data-testid="submit"]');

// The listing opens on a welcome page while no host group exists: its
// create button then opens the same form
const openCreationForm = (): void => {
  cy.visit(PAGES.configuration.hostGroups);
  cy.wait('@getHostGroups').then(({ response }) => {
    if (response?.body.meta.total === 0) {
      cy.getByLabel({ label: 'create', tag: 'button' }).click();

      return;
    }

    cy.getByTestId({ testId: 'add-resource' }).click();
  });

  cy.contains(addFormTitle).should('be.visible');
};

const cancelForm = (): void => {
  cy.get('button[data-testid="cancel"][aria-label="Cancel"]').click();
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('no host group named {string} exists', (name: string) => {
  formHostGroup = name;

  hostGroupsNamed(name).should('have.length', 0);
});

When(
  'the user opens the host group creation form and types an alias without a name',
  () => {
    openCreationForm();

    // Leaving the Name field empty marks it as visited
    nameInput().click();
    aliasInput().type(draftAlias);
  }
);

Then(
  'the {string} field shows {string} and {string} is disabled',
  (field: string, error: string, button: string) => {
    cy.get(`div[data-testid="${field}"]`).should('contain', error);
    cy.get(`input[data-testid="${field}"]`).should(
      'have.attr',
      'aria-invalid',
      'true'
    );

    saveButton().should('have.text', button).and('be.disabled');
  }
);

When('the user cancels the form', () => {
  cancelForm();
});

Then('the dialog {string} lets the user stay on the form', (title: string) => {
  cy.contains(title).should('be.visible');
  cy.getByLabel({ label: 'Stay', tag: 'button' }).click();

  cy.contains(addFormTitle).should('be.visible');
  cy.contains(title).should('not.exist');
  aliasInput().should('have.value', draftAlias);
});

When(
  'the user types the name {string}, cancels the form and discards the changes',
  (name: string) => {
    nameInput().type(name);
    cancelForm();

    cy.contains('Do you want to save the changes?').should('be.visible');
    cy.getByLabel({ label: 'Discard', tag: 'button' }).click();
  }
);

Then('no host group {string} is created', (name: string) => {
  cy.contains('Host groups').should('be.visible');
  cy.contains(addFormTitle).should('not.exist');

  cy.get('@postHostGroup.all').should('have.length', 0);
  hostGroupsNamed(name).should('have.length', 0);
});

When(
  'the user saves the host group {string} with the alias {string}',
  (name: string, alias: string) => {
    openCreationForm();

    nameInput().type(name);
    aliasInput().type(alias);
    saveButton().should('be.enabled').click();

    cy.wait('@postHostGroup').its('response.statusCode').should('eq', 201);
  }
);

Then(
  'the message {string} is displayed and the host group is listed',
  (message: string) => {
    cy.contains(message).should('be.visible');
    cy.contains(addFormTitle).should('not.exist');

    cy.contains('p', formHostGroup).should('be.visible');
    hostGroupsNamed(formHostGroup).should('have.length', 1);
  }
);

When(
  'the user reopens its edit form from the URL and changes the alias to {string}',
  (alias: string) => {
    hostGroupsNamed(formHostGroup).then(([{ hg_id }]) => {
      cy.visit(`${PAGES.configuration.hostGroups}?mode=edit&id=${hg_id}`);
    });
    cy.wait('@getHostGroup').its('response.statusCode').should('eq', 200);

    cy.contains(editFormTitle).should('be.visible');
    nameInput().should('have.value', formHostGroup);

    aliasInput().clear().type(alias);
    saveButton().should('be.enabled').click();

    cy.wait('@putHostGroup').its('response.statusCode').should('eq', 204);
  }
);

Then(
  'the message {string} is displayed and the alias is stored',
  (message: string) => {
    cy.contains(message).should('be.visible');
    cy.contains(editFormTitle).should('not.exist');

    hostGroupsNamed(formHostGroup).then(([{ hg_alias }]) => {
      expect(hg_alias).to.eq('rbt ui form edited');
    });
  }
);

When('the user saves another host group named {string}', (name: string) => {
  openCreationForm();

  nameInput().type(name);
  saveButton().should('be.enabled').click();
});

Then(
  'an error message says that the name already exists and the form stays open',
  () => {
    // AddHostGroup answers an InvalidArgumentResponse: HTTP 400
    cy.wait('@postHostGroup').its('response.statusCode').should('eq', 400);

    cy.contains(`The host group name '${formHostGroup}' already exists`).should(
      'be.visible'
    );

    cy.contains(addFormTitle).should('be.visible');
    nameInput().should('have.value', formHostGroup);
    hostGroupsNamed(formHostGroup).should('have.length', 1);
  }
);
