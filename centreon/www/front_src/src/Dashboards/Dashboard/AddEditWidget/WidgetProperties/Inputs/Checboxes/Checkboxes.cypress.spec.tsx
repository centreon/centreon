import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import WidgetSwitch from '../Switch';
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

const secondaryOptions = [
  {
    id: 'c',
    name: 'C'
  },
  {
    id: 'd',
    name: 'D'
  }
];

const title = 'Title';
const secondaryLabel = ['Secondary label', 'other label'];

interface Props {
  canEdit?: boolean;
  labels?: string | Array<string>;
}

const initializeSimpleCheckboxes = ({
  canEdit = true,
  labels = secondaryLabel[0]
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
              checkbox: []
            }
          }}
          onSubmit={cy.stub()}
        >
          <WidgetCheckboxes
            defaultValue={[]}
            label={title}
            options={primaryOptions}
            propertyName="checkbox"
            secondaryLabel={labels}
          />
        </Formik>
      </Provider>
    )
  });
};

const initializeAdvancedCheckboxes = (dependency: boolean): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, true);
  store.set(isEditingAtom, true);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              checkbox: [],
              dependency
            }
          }}
          onSubmit={cy.stub()}
        >
          <>
            <WidgetSwitch label="Dependency" propertyName="dependency" />
            <WidgetCheckboxes
              defaultValue={{
                is: true,
                otherwise: ['d'],
                then: ['a'],
                when: 'dependency'
              }}
              label={title}
              options={{
                is: true,
                otherwise: secondaryOptions,
                then: primaryOptions,
                when: 'dependency'
              }}
              propertyName="checkbox"
            />
          </>
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

describe('Advanced checkboxes', () => {
  it('displays other options and default value when the dependency value unmeet the condition', () => {
    initializeAdvancedCheckboxes(true);

    cy.findByLabelText('Dependency').click();

    cy.findByLabelText('C', { exact: true }).should('not.be.checked');
    cy.findByLabelText('D', { exact: true }).should('be.checked');

    cy.makeSnapshot();
  });

  it('displays options and default value when the dependency value meet the condition', () => {
    initializeAdvancedCheckboxes(true);

    cy.findByLabelText('Dependency').click();

    cy.findByLabelText('C', { exact: true }).should('be.enabled');
    cy.findByLabelText('D', { exact: true }).should('be.enabled');

    cy.findByLabelText('Dependency').click();

    cy.findByLabelText('A', { exact: true }).should('be.checked');
    cy.findByLabelText('B', { exact: true }).should('not.be.checked');

    cy.makeSnapshot();
  });
});
