import { Formik } from 'formik';

import { editProperties } from '../../../../hooks/useCanEditDashboard';
import WidgetSwitch from '../Switch';

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

interface Props {
  canEdit?: boolean;
}

const initializeSimpleCheckboxes = ({ canEdit = true }: Props): void => {
  cy.stub(editProperties, 'useCanEditProperties').returns({
    canEdit,
    canEditField: canEdit
  });

  cy.mount({
    Component: (
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
          options={primaryOptions}
          propertyName="radio"
        />
      </Formik>
    )
  });
};

const initializeAdvancedCheckboxes = (dependency: boolean): void => {
  cy.stub(editProperties, 'useCanEditProperties').returns({
    canEdit: true,
    canEditField: true
  });

  cy.mount({
    Component: (
      <Formik
        initialValues={{
          moduleName: 'widget',
          options: {
            dependency,
            radio: []
          }
        }}
        onSubmit={cy.stub()}
      >
        <>
          <WidgetSwitch label="Dependency" propertyName="dependency" />
          <WidgetRadio
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
            propertyName="radio"
          />
        </>
      </Formik>
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
});

describe('Radio disabled', () => {
  it('displays checkboxes as disabled', () => {
    initializeSimpleCheckboxes({ canEdit: false });

    cy.findByLabelText('A', { exact: true }).should('be.disabled');
    cy.findByLabelText('B', { exact: true }).should('be.disabled');

    cy.makeSnapshot();
  });
});

describe('Advanced radio', () => {
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
