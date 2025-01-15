import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import { labelDisplayAs } from '../../../../translatedLabels';

import DisplayType from './DisplayType';

const options = [
  {
    icon: '<svg focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="TitleIcon"><path d="M5 4v3h5.5v12h3V7H19V4z"></path></svg>',
    id: 'text',
    label: 'Text'
  },
  {
    icon: '<svg focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="SpeedIcon"><path d="m20.38 8.57-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44zm-9.79 6.84a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"></path></svg>',
    id: 'gauge',
    label: 'Gauge'
  },
  {
    icon: '<svg focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="BarChartIcon"><path d="M4 9h4v11H4zm12 4h4v7h-4zm-6-9h4v16h-4z"></path></svg>',
    id: 'bar',
    label: 'Bar'
  }
];

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
              displayType: 'text'
            }
          }}
          onSubmit={cy.stub()}
        >
          <DisplayType options={options} propertyName="displayType" />
        </Formik>
      </Provider>
    )
  });
};

describe('Display type', () => {
  beforeEach(() => {
    initializeComponent(true);
  });

  it('displays the text option as pre-selected', () => {
    cy.contains(labelDisplayAs).should('be.visible');

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

describe('Disabled display type', () => {
  beforeEach(() => {
    initializeComponent(false);
  });

  it('displays the display types as disabled', () => {
    cy.contains(labelDisplayAs).should('be.visible');

    cy.get('[data-type="text"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="gauge"]').should('have.attr', 'data-disabled', 'true');
    cy.get('[data-type="bar"]').should('have.attr', 'data-disabled', 'true');

    cy.makeSnapshot();
  });
});
