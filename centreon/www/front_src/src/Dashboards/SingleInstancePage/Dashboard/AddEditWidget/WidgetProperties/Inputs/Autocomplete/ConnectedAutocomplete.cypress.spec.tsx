import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { Method, TestQueryProvider } from '@centreon/ui';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import ConnectedAutocomplete from './ConnectedAutocomplete';

const optionsData = {
  meta: {
    limit: 2,
    page: 1,
    total: 2
  },
  result: [
    { id: 0, name: 'My Option 1' },
    { id: 1, name: 'My Option 2' }
  ]
};

const initialize = ({
  isInGroup = false,
  canEdit = true,
  isSingleAutoComplete
}): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.interceptAPIRequest({
    alias: 'getOptions',
    method: Method.GET,
    path: './api/latest/endpoint?page=1',
    response: optionsData
  });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Formik
            initialValues={{
              moduleName: 'widget',
              options: {
                test: undefined
              }
            }}
            onSubmit={cy.stub()}
          >
            <ConnectedAutocomplete
              baseEndpoint="/endpoint"
              isInGroup={isInGroup}
              isSingleAutocomplete={isSingleAutoComplete}
              label="Autocomplete"
              propertyName="test"
              secondaryLabel="Label"
              type=""
            />
          </Formik>
        </Provider>
      </TestQueryProvider>
    )
  });
};

describe('Widget connected autocomplete', () => {
  it('displays a single autocomplete and selects a value when an options is chosen', () => {
    initialize({ isSingleAutoComplete: true });

    cy.findByLabelText('Label').click();
    cy.waitForRequest('@getOptions');
    cy.contains('My Option 2').click();

    cy.findByLabelText('Label').should('have.value', 'My Option 2');

    cy.makeSnapshot();
  });

  it('displays the multi autocomplete and select values when options are chosen', () => {
    initialize({ isSingleAutoComplete: false });

    cy.findByLabelText('Label').click();
    cy.waitForRequest('@getOptions');
    cy.contains('My Option 1').click();
    cy.contains('My Option 2').click();

    cy.makeSnapshot();
  });

  it('deletes a selected option on multi autocomplete when the corresponding button is clicked', () => {
    initialize({ isSingleAutoComplete: false });

    cy.findByLabelText('Label').click();
    cy.waitForRequest('@getOptions');
    cy.contains('My Option 1').click();
    cy.contains('My Option 2').click();
    cy.findAllByTestId('CancelIcon').eq(1).click();
    cy.contains('Autocomplete').click();

    cy.contains('My Option 2').should('not.exist');

    cy.makeSnapshot();
  });

  it('disables the autocomplete when the does have the right to edit it', () => {
    initialize({ canEdit: false });

    cy.findByLabelText('Label').should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays the title in group mode when a prop is set', () => {
    initialize({ isInGroup: true });

    cy.contains('Autocomplete').should('be.visible');

    cy.makeSnapshot();
  });
});
