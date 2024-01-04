import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '../../..';

import { AccessRights } from './AccessRights';
import {
  buildResult,
  emptyAccessRights,
  labels,
  removedAccessRights,
  roles,
  simpleAccessRights,
  updatedAccessRights
} from './storiesData';

const initialize = ({
  initialValues = simpleAccessRights,
  loading = false,
  link = 'link'
}): unknown => {
  const cancel = cy.stub();
  const save = cy.stub();

  cy.interceptAPIRequest({
    alias: 'getContacts',
    method: Method.GET,
    path: '**/contacts?**',
    response: buildResult(false)
  });
  cy.interceptAPIRequest({
    alias: 'getContactGroups',
    method: Method.GET,
    path: '**/contact-groups?**',
    response: buildResult(true)
  });

  cy.mount({
    Component: (
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={createStore()}>
            <AccessRights
              cancel={cancel}
              endpoints={{
                contact: '/contacts',
                contactGroup: '/contact-groups'
              }}
              initialValues={initialValues}
              labels={labels}
              link={link}
              loading={loading}
              roles={roles}
              submit={save}
            />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    )
  });

  return {
    cancel,
    save
  };
};

describe('Access rights', () => {
  it('displays the access rights in loading state', () => {
    initialize({ loading: true });

    cy.contains('Share dashboard with').should('be.visible');
    cy.contains('Contact').should('be.visible');
    cy.contains('Contact group').should('be.visible');
    cy.findByLabelText('Add a contact').should('be.visible');
    cy.findByTestId('add_role').should('be.disabled');
    cy.findByTestId('add').should('be.disabled');
    cy.findByLabelText('Copy link').should('be.visible');
    cy.findByLabelText('Cancel').should('be.visible');
    cy.findByLabelText('Save').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the access rights without link', () => {
    initialize({ link: null });

    cy.findByLabelText('Copy link').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the access rights with an empty list', () => {
    initialize({ initialValues: emptyAccessRights });

    cy.contains('The contact list is empty').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the access rights with an empty list', () => {
    initialize({});

    simpleAccessRights.forEach(({ name, email, isContactGroup, role }) => {
      cy.contains(name).should('be.visible');
      cy.contains(email).should('be.visible');
      cy.get(`[data-groupChip="${name}"]`).should(
        isContactGroup ? 'be.visible' : 'not.exist'
      );
      cy.findByTestId(`role-${name}`).should('have.value', role);
      cy.findByTestId(`remove-${name}`).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('displays a removed chip when the corresponding icon is clicked', () => {
    initialize({});

    cy.findByTestId(`remove-Leah McGlynn`).should(
      'have.attr',
      'data-removed',
      'false'
    );

    cy.findByTestId(`remove-Leah McGlynn`).click();

    cy.findByTestId(`remove-Leah McGlynn`).should(
      'have.attr',
      'data-removed',
      'true'
    );
    cy.contains(labels.list.removed).should('be.visible');

    cy.makeSnapshot();
  });

  it('restores the contact when the contact is removed and the corresponding icon is clicked', () => {
    initialize({});

    cy.findByTestId(`remove-Leah McGlynn`).click();

    cy.findByTestId(`remove-Leah McGlynn`).should(
      'have.attr',
      'data-removed',
      'true'
    );
    cy.contains(labels.list.removed).should('be.visible');

    cy.findByTestId(`remove-Leah McGlynn`).click();

    cy.findByTestId(`remove-Leah McGlynn`).should(
      'have.attr',
      'data-removed',
      'false'
    );
    cy.contains(labels.list.removed).should('not.exist');

    cy.makeSnapshot();
  });

  it('submits the new acces rights list without the removed contact', () => {
    const { save } = initialize({});

    cy.findByTestId(`remove-Leah McGlynn`).click();

    cy.findByTestId(`remove-Leah McGlynn`).should(
      'have.attr',
      'data-removed',
      'true'
    );
    cy.contains(labels.list.removed).should('be.visible');
    cy.contains('1 removed').should('be.visible');

    cy.contains(labels.actions.save)
      .click()
      .then(() => {
        expect(save).to.have.been.calledWith(removedAccessRights);
      });

    cy.makeSnapshot();
  });

  it('submits the new acces rights list with the updated contact', () => {
    const { save } = initialize({});

    cy.findByTestId(`role-Leah McGlynn`).parent().click();

    cy.get('li[data-value="editor"]').click();
    cy.contains(labels.list.updated).should('be.visible');
    cy.contains('1 updated').should('be.visible');

    cy.contains(labels.actions.save)
      .click()
      .then(() => {
        expect(save).to.have.been.calledWith(updatedAccessRights);
      });

    cy.makeSnapshot();
  });

  it('removes the updated chip when the contact role is updated and its initial role is assigned back', () => {
    initialize({});

    cy.findByTestId(`role-Leah McGlynn`).parent().click();

    cy.get('li[data-value="editor"]').click();
    cy.contains(labels.list.updated).should('be.visible');

    cy.get('li[data-value="viewer"]').click();
    cy.contains(labels.list.updated).should('not.exist');

    cy.makeSnapshot();
  });

  it('retrieves contacts when the contact radio is selected', () => {
    initialize({});

    cy.contains(labels.add.contact).click();
    cy.findByLabelText(labels.add.autocompleteContact).click();

    cy.waitForRequest('@getContacts');

    cy.makeSnapshot();
  });

  it('retrieves contact groups when the contact group radio is selected', () => {
    initialize({});

    cy.contains(labels.add.contactGroup).click();
    cy.findByLabelText(labels.add.autocompleteContactGroup).click();

    cy.waitForRequest('@getContactGroups');

    cy.makeSnapshot();
  });

  it('adds the contact as viewer when a contact is selected and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labels.add.contact).click();
    cy.findByLabelText(labels.add.autocompleteContact).click();

    cy.waitForRequest('@getContacts');

    cy.contains('Entity 10').click();

    cy.findByTestId('add').click();

    cy.contains('Entity 10').should('be.visible');

    cy.findByTestId('role-Entity 10').should('have.value', 'viewer');
    cy.contains(labels.list.added).should('be.visible');

    cy.makeSnapshot();
  });

  it('adds the contact group as editor when a contact group is selected and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labels.add.contactGroup).click();
    cy.findByLabelText(labels.add.autocompleteContactGroup).click();

    cy.waitForRequest('@getContactGroups');

    cy.contains('Entity Group 10').click();
    cy.findByTestId('add_role').parent().click();
    cy.get('li[data-value="editor"]').click();
    cy.findByTestId('add').click();

    cy.contains('Entity Group 10').should('be.visible');

    cy.findByTestId('role-Entity Group 10').should('have.value', 'editor');
    cy.contains(labels.list.added).should('be.visible');
    cy.contains('1 added').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays statistics when a contact is added, a contact is updated and a contact is removed', () => {
    initialize({});

    cy.contains(labels.add.contactGroup).click();
    cy.findByLabelText(labels.add.autocompleteContactGroup).click();

    cy.waitForRequest('@getContactGroups');

    cy.contains('Entity Group 10').click();
    cy.findByTestId('add_role').parent().click();
    cy.get('li[data-value="editor"]').click();
    cy.findByTestId('add').click();

    cy.contains('Entity Group 10').should('be.visible');

    cy.findByTestId(`role-Leah McGlynn`).parent().click();
    cy.get('li[data-value="editor"]').click();

    cy.findByTestId('remove-Jody Blanda').click();

    cy.contains('1 added | 1 updated | 1 removed').should('be.visible');

    cy.makeSnapshot();
  });

  it('clears the autocomplete when a contact is selected and the contact type is changed', () => {
    initialize({});

    cy.contains(labels.add.contact).click();
    cy.findByLabelText(labels.add.autocompleteContact).click();

    cy.waitForRequest('@getContacts');

    cy.contains('Entity 10').click();
    cy.findByLabelText(labels.add.autocompleteContact).should(
      'have.value',
      'Entity  10'
    );

    cy.contains(labels.add.contactGroup).click();
    cy.findByLabelText(labels.add.autocompleteContactGroup).should(
      'have.value',
      ''
    );

    cy.makeSnapshot();
  });

  it('removes the contact from the list when the contact has been to the list and the corresponding button is clicked', () => {
    initialize({});

    cy.contains(labels.add.contact).click();
    cy.findByLabelText(labels.add.autocompleteContact).click();

    cy.waitForRequest('@getContacts');

    cy.contains('Entity 10').click();

    cy.findByTestId('add').click();

    cy.contains('Entity 10').should('be.visible');

    cy.contains(labels.list.added).should('be.visible');

    cy.findByTestId('remove-Entity 10').click();

    cy.contains('Entity 10').should('not.exist');
    cy.contains(labels.list.added).should('not.exist');

    cy.makeSnapshot();
  });
});
