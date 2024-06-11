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

    cy.findAllByLabelText('A', { exact: true }).eq(0).should('be.enabled');
    cy.findAllByLabelText('B', { exact: true }).eq(0).should('be.enabled');
    cy.findAllByLabelText('A', { exact: true }).eq(0).should('not.be.checked');
    cy.findAllByLabelText('B', { exact: true }).eq(0).should('not.be.checked');

    cy.makeSnapshot();
  });

  it('checks an option when an option is clicked', () => {
    initializeSimpleCheckboxes({});

    cy.findAllByLabelText('A', { exact: true }).eq(0).click();

    cy.findAllByLabelText('A', { exact: true }).eq(0).should('be.checked');

    cy.makeSnapshot();
  });

  it('does not display options when the option list is empty', () => {
    initializeSimpleCheckboxes({ hasOptions: false });

    cy.findAllByLabelText('A', { exact: true }).should('have.length', 0);

    cy.makeSnapshot();
  });
});

describe('Radio disabled', () => {
  it('displays checkboxes as disabled', () => {
    initializeSimpleCheckboxes({ canEdit: false });

    cy.findAllByLabelText('A', { exact: true }).eq(0).should('be.disabled');
    cy.findAllByLabelText('B', { exact: true }).eq(0).should('be.disabled');

    cy.makeSnapshot();
  });
});
