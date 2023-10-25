import { Formik } from 'formik';

import { labelDisplayType } from '../../../../translatedLabels';
import { editProperties } from '../../../../hooks/useCanEditDashboard';

import SingleMetricGraphType from './SingleMetricGraphType';

const initializeComponent = (): void => {
  cy.mount({
    Component: (
      <Formik
        initialValues={{
          moduleName: 'widget',
          options: {
            singleMetricGraphType: 'text'
          }
        }}
        onSubmit={cy.stub()}
      >
        <SingleMetricGraphType label="" propertyName="singleMetricGraphType" />
      </Formik>
    )
  });
};

describe('Single metric graph type', () => {
  beforeEach(() => {
    cy.stub(editProperties, 'useCanEditProperties').returns({
      canEdit: true,
      canEditField: true
    });
    initializeComponent();
  });

  it('displays the text option as pre-selected', () => {
    cy.contains(labelDisplayType).should('be.visible');

    cy.get('[data-type="text"]').should('have.attr', 'data-selected', 'true');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-selected', 'false');
    cy.get('[data-type="bar"]').should('have.attr', 'data-selected', 'false');

    cy.makeSnapshot();
  });

  it('marks the gauge option as selected when clicked', () => {
    cy.get('[data-type="gauge"]').click();

    cy.get('[data-type="text"]').should('have.attr', 'data-selected', 'false');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-selected', 'true');
    cy.get('[data-type="bar"]').should('have.attr', 'data-selected', 'false');

    cy.makeSnapshot();
  });
});

describe('Disabled Graph type', () => {
  beforeEach(() => {
    cy.stub(editProperties, 'useCanEditProperties').returns({
      canEdit: true,
      canEditField: false
    });
    initializeComponent();
  });

  it('displays the graph types as disabled', () => {
    cy.contains(labelDisplayType).should('be.visible');

    cy.get('[data-type="text"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="bar"]').should('have.attr', 'data-disabled', 'true');

    cy.makeSnapshot();
  });
});
