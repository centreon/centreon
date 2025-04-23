import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import WidgetCheckboxes from './Chekboxes';
import { labelSelectAll, labelUnselectAll } from './translatedLabels';

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
const secondaryLabel = ['Secondary label', 'other label'];

interface Props {
  areValuesEmpty?: boolean;
  canEdit?: boolean;
  hasOptions?: boolean;
  keepOneOptionSelected?: boolean;
  labels?: string | Array<string>;
}

const initializeSimpleCheckboxes = ({
  canEdit = true,
  labels = secondaryLabel[0],
  hasOptions = true,
  keepOneOptionSelected,
  areValuesEmpty = false
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
            options: areValuesEmpty
              ? undefined
              : {
                  checkbox: keepOneOptionSelected ? ['a', 'b'] : []
                }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetCheckboxes
            defaultValue={[]}
            keepOneOptionSelected={keepOneOptionSelected}
            label={title}
            options={hasOptions ? primaryOptions : undefined}
            propertyName="checkbox"
            secondaryLabel={labels}
            type=""
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Simple checkboxes', () => {
  it('displays checkboxes', () => {
    initializeSimpleCheckboxes({});

    cy.contains(title).should('be.visible');

    cy.findByLabelText(secondaryLabel[0]).trigger('mouseover');
    cy.contains(secondaryLabel[0]).should('be.visible');

    cy.contains(labelSelectAll).should('be.enabled');

    cy.findByLabelText('A', { exact: true }).should('be.enabled');
    cy.findByLabelText('B', { exact: true }).should('be.enabled');
    cy.findByLabelText('A', { exact: true }).should('not.be.checked');
    cy.findByLabelText('B', { exact: true }).should('not.be.checked');

    cy.makeSnapshot();
  });

  it('display multiple secondary labels when the icon hovered', () => {
    initializeSimpleCheckboxes({ labels: secondaryLabel });

    cy.findByLabelText(secondaryLabel[0]).trigger('mouseover');
    cy.contains(secondaryLabel[0]).should('be.visible');
    cy.contains(secondaryLabel[1]).should('be.visible');

    cy.makeSnapshot();
  });

  it('checks an option when an option is clicked', () => {
    initializeSimpleCheckboxes({});

    cy.findByLabelText('A', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');

    cy.makeSnapshot();
  });

  it('checks all the options when the corresponding button is clicked', () => {
    initializeSimpleCheckboxes({});

    cy.contains(labelSelectAll).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');
    cy.findByLabelText('B', { exact: true }).should('be.checked');

    cy.makeSnapshot();
  });

  it('unchecks all the options when all the options are checked and the corresponding button is clicked', () => {
    initializeSimpleCheckboxes({});

    cy.contains(labelSelectAll).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');
    cy.findByLabelText('B', { exact: true }).should('be.checked');

    cy.contains(labelUnselectAll).click();

    cy.findByLabelText('A', { exact: true }).should('not.be.checked');
    cy.findByLabelText('B', { exact: true }).should('not.be.checked');

    cy.makeSnapshot();
  });

  it('does not display options when the option list is empty', () => {
    initializeSimpleCheckboxes({ hasOptions: false });

    cy.contains(labelUnselectAll).should('not.exist');
    cy.contains(labelSelectAll).should('not.exist');
    cy.findByLabelText('A', { exact: true }).should('not.exist');

    cy.makeSnapshot();
  });

  it('cannot unchecks the last checked option when the corresponding prop is set', () => {
    initializeSimpleCheckboxes({ keepOneOptionSelected: true });

    cy.findByLabelText('B', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');

    cy.findByLabelText('A', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');

    cy.makeSnapshot();
  });

  it('selects all options when the data is empty and options are clicked', () => {
    initializeSimpleCheckboxes({ areValuesEmpty: true });

    cy.findByLabelText('A', { exact: true }).click();
    cy.findByLabelText('B', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');
    cy.findByLabelText('B', { exact: true }).should('be.checked');

    cy.findByLabelText('A', { exact: true }).click();

    cy.findByLabelText('A', { exact: true }).should('not.be.checked');
  });
});

describe('Checkbox disabled', () => {
  it('displays checkboxes as disabled', () => {
    initializeSimpleCheckboxes({ canEdit: false });

    cy.contains(labelSelectAll).should('be.disabled');

    cy.findByLabelText('A', { exact: true }).should('be.disabled');
    cy.findByLabelText('B', { exact: true }).should('be.disabled');

    cy.makeSnapshot();
  });
});
