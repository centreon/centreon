import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../atoms';

import WidgetWarningField from './Warning';

interface Props {
  label?: string;
}

const initialize = ({ label = 'Text' }: Props): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, true);
  store.set(isEditingAtom, true);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {}
          }}
          onSubmit={cy.stub()}
        >
          <WidgetWarningField label={label} />
        </Formik>
      </Provider>
    )
  });
};

const label = 'Warning message!';

describe('WidgetTextField', () => {
  it('displays the text field', () => {
    initialize({ label });

    cy.contains(label).should('be.visible');

    cy.makeSnapshot();
  });
});
