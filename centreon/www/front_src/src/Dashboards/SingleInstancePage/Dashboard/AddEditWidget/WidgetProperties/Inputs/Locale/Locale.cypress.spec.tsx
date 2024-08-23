import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import { labelSelectTimeFormat } from '../../../../translatedLabels';

import Locale from './Locale';

const initialize = ({ canEdit = true, hasValue = false }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);
  store.set(userAtom, { locale: 'de-DE' });

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              test: hasValue ? { id: 'de_DE', name: 'de_DE' } : null
            }
          }}
          onSubmit={cy.stub()}
        >
          <Locale propertyName="test" />
        </Formik>
      </Provider>
    )
  });
};

describe('Locale', () => {
  it('displays the user locale as pre-selected when any value was previously selected', () => {
    initialize({});

    cy.findByTestId(labelSelectTimeFormat).should(
      'have.value',
      'German (Germany) (de-DE)'
    );

    cy.makeSnapshot();
  });

  it('selects a new locale when a locale is selected', () => {
    initialize({ hasValue: true });

    cy.findByTestId(labelSelectTimeFormat).click();
    cy.contains('English (United Kingdom)').click();
    cy.findByTestId(labelSelectTimeFormat).should(
      'have.value',
      'English (United Kingdom) (en-GB)'
    );

    cy.makeSnapshot();
  });

  it('displays the autocomplete as disabled when the user cannot edit the field', () => {
    initialize({ canEdit: false, hasValue: true });

    cy.findByTestId(labelSelectTimeFormat).should('be.disabled');

    cy.makeSnapshot();
  });
});
