import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import Select from './Select';

const initializeComponent = (canEdit = true): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.clock(new Date(2023, 5, 5, 8, 0, 0).getTime());
  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              select: 'option1'
            }
          }}
          onSubmit={cy.stub()}
        >
          <Select
            label="Select"
            options={[
              {
                id: 'option1',
                name: 'Option 1'
              },
              {
                id: 'option2',
                name: 'Option 2'
              }
            ]}
            propertyName="select"
            type="select"
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Select', () => {
  beforeEach(() => {
    initializeComponent();
  });

  it('displays the select with a default value', () => {
    cy.contains('Select').should('be.visible');
    cy.findByTestId('Select').should('have.value', 'option1');

    cy.makeSnapshot();
  });

  it('changes the select value when another option is selected from the dropdown', () => {
    cy.findByTestId('Select').should('have.value', 'option1');
    cy.findByTestId('Select').parent().click();
    cy.contains('Option 2').click();
    cy.findByTestId('Select').should('have.value', 'option2');

    cy.makeSnapshot();
  });
});

describe('Select disabled', () => {
  beforeEach(() => initializeComponent(false));

  it('displays the select field as disabled when the user is not allowed to edit field', () => {
    cy.findByTestId('Select').should('be.disabled');

    cy.makeSnapshot();
  });
});
