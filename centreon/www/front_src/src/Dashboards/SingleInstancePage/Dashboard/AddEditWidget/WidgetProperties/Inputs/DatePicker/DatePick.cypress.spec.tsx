import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import DatePicker from './DatePicker';

const initialize = ({ canEdit = true, hasValue = false }): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);
  store.set(userAtom, { locale: 'fr_FR', timezone: 'Europe/London' });
  cy.clock(new Date(2024, 9, 29));

  cy.clock(new Date().getTime());
  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              test: hasValue ? 1722366095000 : null
            }
          }}
          onSubmit={cy.stub()}
        >
          <DatePicker
            datePicker={{ maxDays: 20 }}
            isInGroup={false}
            propertyName="test"
          />
        </Formik>
      </Provider>
    )
  });
};

describe('DatePicker', () => {
  it('selects date time when the picker is opened and a date time is picked', () => {
    initialize({});

    cy.findByTestId('CalendarIcon').click();
    cy.contains('31').click();
    cy.contains('01').click();
    cy.contains('40').click();
    cy.contains('OK').click();

    cy.get('input').should('have.value', '31/10/2024 04:00');

    cy.makeSnapshot();
  });

  it('displays a date time with a pre-selected date time', () => {
    initialize({ hasValue: true });

    cy.get('input').should('have.value', '30/07/2024 20:01');

    cy.makeSnapshot();
  });

  it('display the picker is disabled when the user cannot edit', () => {
    initialize({ canEdit: false });

    cy.get('input').should('be.disabled');

    cy.makeSnapshot();
  });
});
