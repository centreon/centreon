import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import { labelValueFormat } from '../../../../translatedLabels';

import WidgetValueFormat from './ValueFormat';

const initialize = ({ isEditing, hasEditPermission }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, hasEditPermission);
  store.set(isEditingAtom, isEditing);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            options: {
              format: 'human'
            }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetValueFormat label="" propertyName="format" />
        </Formik>
      </Provider>
    )
  });
};

describe('Value format', () => {
  beforeEach(() => initialize({ hasEditPermission: true, isEditing: true }));

  it('displays a radio button as selected when the value is set', () => {
    cy.contains(labelValueFormat).should('be.visible');
    cy.findByTestId('human').find('input').should('be.checked');
    cy.findByTestId('raw').find('input').should('not.be.checked');

    cy.makeSnapshot();
  });

  it('changes the value when a radio button is clicked', () => {
    cy.findByTestId('raw').click();

    cy.findByTestId('human').find('input').should('not.be.checked');
    cy.findByTestId('raw').find('input').should('be.checked');

    cy.makeSnapshot();
  });
});

describe('Value format: disabled', () => {
  it('displays radio buttons as disabled when the field is not being edited', () => {
    initialize({ hasEditPermission: true, isEditing: false });

    cy.contains(labelValueFormat).should('be.visible');
    cy.findByTestId('human').find('input').should('be.disabled');
    cy.findByTestId('raw').find('input').should('be.disabled');

    cy.makeSnapshot();
  });

  it('displays radio buttons as disabled when permission are not sufficient', () => {
    initialize({ hasEditPermission: false, isEditing: false });

    cy.contains(labelValueFormat).should('be.visible');
    cy.findByTestId('human').find('input').should('be.disabled');
    cy.findByTestId('raw').find('input').should('be.disabled');

    cy.makeSnapshot();
  });
});
