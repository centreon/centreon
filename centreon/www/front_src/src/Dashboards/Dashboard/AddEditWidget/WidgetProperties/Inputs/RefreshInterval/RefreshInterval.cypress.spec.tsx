import { Formik } from 'formik';

import {
  labelInterval,
  labelRefreshInterval
} from '../../../../translatedLabels';

import RefreshInterval from './RefreshInterval';

const initializeComponent = (): void => {
  cy.mount({
    Component: (
      <Formik
        initialValues={{
          moduleName: 'widget',
          options: {
            refreshInterval: 'default',
            refreshIntervalCount: null
          }
        }}
        onSubmit={cy.stub()}
      >
        <RefreshInterval label="" propertyName="refreshInterval" />
      </Formik>
    )
  });
};

describe('Refresh interval', () => {
  beforeEach(() => {
    initializeComponent();
  });
  it('displays the refresh interval fields', () => {
    cy.contains(labelRefreshInterval).should('be.visible');
    cy.findByTestId('default').should('be.visible');
    cy.findByTestId('custom').should('be.visible');
    cy.findAllByTestId(labelInterval).eq(1).should('be.disabled');
    cy.findByTestId('manual').should('be.visible');

    cy.makeSnapshot();
  });

  it('enables the Interval field when the Custom radio button is selected', () => {
    cy.findByTestId('custom').click();
    cy.findAllByTestId(labelInterval).eq(1).should('be.enabled');

    cy.makeSnapshot();
  });

  it('changes the "second" label to "seconds" when the value is greater than 1', () => {
    cy.findByTestId('custom').click();
    cy.findAllByTestId(labelInterval).eq(1).type('2');
    cy.findAllByText('second').should('not.exist');
    cy.findAllByText('seconds').should('exist');

    cy.makeSnapshot();
  });

  it('changes the "seconds" label to "second" when the value is 1', () => {
    cy.findByTestId('custom').click();
    cy.findAllByTestId(labelInterval).eq(1).type('1');
    cy.findAllByText('second').should('exist');
    cy.findAllByText('seconds').should('not.exist');

    cy.makeSnapshot();
  });
});
