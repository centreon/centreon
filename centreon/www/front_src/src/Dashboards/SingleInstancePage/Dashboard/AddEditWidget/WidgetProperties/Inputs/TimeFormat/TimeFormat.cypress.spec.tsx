import { createStore, Provider } from 'jotai';
import { Formik } from 'formik';

import { userAtom } from '@centreon/ui-context';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import TimeFormat from './TimeFormat';

const initialize = ({ canEdit = true, hasValue = false }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);
  store.set(userAtom, { locale: 'fr_FR' });

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              test: hasValue ? '12' : null
            }
          }}
          onSubmit={cy.stub()}
        >
          <TimeFormat propertyName="test" />
        </Formik>
      </Provider>
    )
  });
};

describe('Time format', () => {
  it('displays time format buttons with user locale', () => {
    initialize({});

    cy.contains('24 hours')
      .should('have.attr', 'data-selected')
      .and('equal', 'true');
  });

  it('displays time format buttons with pre-selected value', () => {
    initialize({ hasValue: true });

    cy.contains('12 hours')
      .should('have.attr', 'data-selected')
      .and('equal', 'true');
  });
});
