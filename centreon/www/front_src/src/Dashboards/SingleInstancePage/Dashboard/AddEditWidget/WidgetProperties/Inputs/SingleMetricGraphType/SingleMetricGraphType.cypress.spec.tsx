import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { labelDisplayType } from '../../../../translatedLabels';
import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';

import SingleMetricGraphType from './SingleMetricGraphType';

const initializeComponent = (canEdit = true): void => {
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
              singleMetricGraphType: 'text'
            }
          }}
          onSubmit={cy.stub()}
        >
          <SingleMetricGraphType
            label=""
            propertyName="singleMetricGraphType"
          />
        </Formik>
      </Provider>
    )
  });
};

describe('Single metric graph type', () => {
  beforeEach(() => {
    initializeComponent(true);
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
    initializeComponent(false);
  });

  it('displays the graph types as disabled', () => {
    cy.contains(labelDisplayType).should('be.visible');

    cy.get('[data-type="text"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="bar"]').should('have.attr', 'data-disabled', 'true');

    cy.makeSnapshot();
  });
});
