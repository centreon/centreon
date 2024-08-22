import { createStore, Provider } from 'jotai';
import { Formik } from 'formik';

import { userAtom } from '@centreon/ui-context';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import { labelSelectTimezone } from '../../../../translatedLabels';

import Timezone from './Timezone';

const initialize = ({ canEdit = true, hasValue = false }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);
  store.set(userAtom, { timezone: 'Europe/Helsinki' });

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              test: hasValue
                ? { id: 'Europe/Helsinki', name: 'Europe/Helsinki' }
                : null
            }
          }}
          onSubmit={cy.stub()}
        >
          <Timezone propertyName="test" />
        </Formik>
      </Provider>
    )
  });
};

describe('Timezone', () => {
  it('displays the user timezone as pre-selected when any value was previously selected', () => {
    initialize({});

    cy.findByTestId(labelSelectTimezone).should(
      'have.value',
      'Europe/Helsinki'
    );

    cy.makeSnapshot();
  });

  it('selects a new timezone when a time zone is selected', () => {
    initialize({ hasValue: true });

    cy.findByTestId(labelSelectTimezone).click();
    cy.contains('Africa/Cairo').click();
    cy.findByTestId(labelSelectTimezone).should('have.value', 'Africa/Cairo');

    cy.makeSnapshot();
  });

  it('displays the autocomplete as disabled when the user cannot edit the field', () => {
    initialize({ canEdit: false, hasValue: true });

    cy.findByTestId(labelSelectTimezone).should('be.disabled');

    cy.makeSnapshot();
  });
});
