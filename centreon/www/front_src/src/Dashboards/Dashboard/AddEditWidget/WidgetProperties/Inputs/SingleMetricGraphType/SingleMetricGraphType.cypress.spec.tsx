import { Formik } from 'formik';

import { labelGraphType } from '../../../../translatedLabels';

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
    initializeComponent();
  });

  it('displays the text option as pre-selected', () => {
    cy.contains(labelGraphType).should('be.visible');

    cy.get('[data-type="text"]').should('have.attr', 'data-selected', 'true');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-selected', 'false');
    cy.get('[data-type="bar"]').should('have.attr', 'data-selected', 'false');

    cy.matchImageSnapshot();
  });

  it('marks the gauge option as selected when clicked', () => {
    cy.get('[data-type="gauge"]').click();

    cy.get('[data-type="text"]').should('have.attr', 'data-selected', 'false');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-selected', 'true');
    cy.get('[data-type="bar"]').should('have.attr', 'data-selected', 'false');

    cy.matchImageSnapshot();
  });
});
