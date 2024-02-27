import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import WidgetRadio from './Radio';

const primaryOptions = [
  {
    id: 'a',
    name: 'A'
  },
  {
    id: 'b',
    name: 'B'
  }
];

const title = 'Title';

interface Props {
  canEdit?: boolean;
  hasOptions?: boolean;
}

const initializeSimpleCheckboxes = ({
  canEdit = true,
  hasOptions = true
}: Props): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEdit);
  store.set(isEditingAtom, canEdit);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              radio: []
            }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetRadio
            defaultValue={[]}
            label={title}
            options={hasOptions ? primaryOptions : undefined}
            propertyName="radio"
            type=""
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Simple radio', () => {
  it('displays radio', () => {
    initializeSimpleCheckboxes({});

    cy.contains(title).should('be.visible');

    cy.findByLabelText('A', { exact: true }).should('be.enabled');
    cy.findByLabelText('B', { exact: true }).should('be.enabled');
    cy.findByLabelText('A', { exact: true }).should('not.be.checked');
    cy.findByLabelText('B', { exact: true }).should('not.be.checked');

    cy.makeSnapshot();
  });

  it('checks an option when an option is clicked', () => {
    initializeSimpleCheckboxes({});

    cy.findByLabelText('A', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');

    cy.makeSnapshot();
  });

  it('does not display options when the option list is empty', () => {
    initializeSimpleCheckboxes({ hasOptions: false });

    cy.findByLabelText('A', { exact: true }).should('not.exist');

    cy.makeSnapshot();
  });
});

describe('Radio disabled', () => {
  it('displays checkboxes as disabled', () => {
    initializeSimpleCheckboxes({ canEdit: false });

    cy.findByLabelText('A', { exact: true }).should('be.disabled');
    cy.findByLabelText('B', { exact: true }).should('be.disabled');

    cy.makeSnapshot();
  });
});
