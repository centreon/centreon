import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import { hasEditPermissionAtom, isEditingAtom } from '../../../../atoms';
import {
  labelBottom,
  labelDisplay,
  labelHosts,
  labelNumberOfValues,
  labelShowValueLabels,
  labelTop
} from '../../../../translatedLabels';

import TopBottomSettings from './TopBottomSettings';

const initializeComponent = (canEditField = true): void => {
  const store = createStore();

  store.set(hasEditPermissionAtom, canEditField);
  store.set(isEditingAtom, canEditField);

  cy.mount({
    Component: (
      <Provider store={store}>
        <Formik
          initialValues={{
            moduleName: 'widget',
            options: {
              topBottomSettings: {
                numberOfValues: 10,
                order: 'top',
                showLabels: true
              }
            }
          }}
          onSubmit={cy.stub()}
        >
          <TopBottomSettings label="" propertyName="topBottomSettings" />
        </Formik>
      </Provider>
    )
  });
};

describe('TopBottomSettings', () => {
  it('displays the top bottom settings with default values', () => {
    initializeComponent();

    cy.contains(labelDisplay).should('be.visible');
    cy.findByLabelText(labelNumberOfValues).should('have.value', '10');
    cy.contains(labelHosts).should('be.visible');
    cy.findByTestId(labelTop).should('have.attr', 'aria-pressed', 'true');
    cy.findByLabelText(labelShowValueLabels).should('be.checked');

    cy.makeSnapshot();
  });

  it('displays the bottom button as selected when the corresponding button is clicked', () => {
    initializeComponent();

    cy.findByTestId(labelBottom).click();
    cy.findByTestId(labelBottom).should('have.attr', 'aria-pressed', 'true');
    cy.findByTestId(labelTop).should('have.attr', 'aria-pressed', 'false');

    cy.makeSnapshot();
  });

  it('displays the number of values when the value is changed', () => {
    initializeComponent();

    cy.findByLabelText(labelNumberOfValues).type('{selectall}').type('5');

    cy.findByLabelText(labelNumberOfValues).should('have.value', '5');

    cy.makeSnapshot();
  });

  it('enforces the value to be 1 when the user manually enters 0', () => {
    initializeComponent();

    cy.findByLabelText(labelNumberOfValues).type('{selectall}').type('0');

    cy.findByLabelText(labelNumberOfValues).should('have.value', '1');

    cy.makeSnapshot();
  });

  it('unchecks the show value labels when the switch is clicked', () => {
    initializeComponent();

    cy.findByLabelText(labelShowValueLabels).click();
    cy.findByLabelText(labelShowValueLabels).should('not.be.checked');

    cy.makeSnapshot();
  });
});

describe('TopBottomSettings disabled', () => {
  it('displays the top bottom settings with default values', () => {
    initializeComponent(false);

    cy.findByLabelText(labelNumberOfValues).should('be.disabled');
    cy.findByTestId(labelTop).should('have.attr', 'aria-pressed', 'true');
    cy.findByTestId(labelTop).should('be.disabled');
    cy.findByTestId(labelBottom).should('be.disabled');
    cy.findByLabelText(labelShowValueLabels).should('be.disabled');

    cy.makeSnapshot();
  });
});
