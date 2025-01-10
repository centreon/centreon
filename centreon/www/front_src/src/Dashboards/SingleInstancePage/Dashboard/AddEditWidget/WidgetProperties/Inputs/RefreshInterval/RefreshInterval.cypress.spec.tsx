import { Formik } from 'formik';
import { Provider, createStore } from 'jotai';

import {
  dashboardRefreshIntervalAtom,
  hasEditPermissionAtom,
  isEditingAtom
} from '../../../../atoms';
import {
  labelDashboardGlobalInterval,
  labelInterval,
  labelRefreshInterval
} from '../../../../translatedLabels';

import RefreshInterval from './RefreshInterval';

const initializeComponent = (
  refreshInterval: {
    interval: number | null;
    type: 'global' | 'manual';
  } = {
    interval: null,
    type: 'global' as const
  }
): void => {
  const store = createStore();

  store.set(dashboardRefreshIntervalAtom, refreshInterval);
  store.set(hasEditPermissionAtom, true);
  store.set(isEditingAtom, true);

  cy.mount({
    Component: (
      <Provider store={store}>
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
      </Provider>
    )
  });
};

describe('Refresh interval', () => {
  beforeEach(() => {
    initializeComponent();
  });
  it('displays the refresh interval fields', () => {
    cy.contains(labelRefreshInterval).should('be.visible');
    cy.contains(`${labelDashboardGlobalInterval} (15 seconds)`).should(
      'be.visible'
    );
    cy.findByTestId('default').should('be.visible');
    cy.findByTestId('custom').should('be.visible');
    cy.findByTestId('manual').should('be.visible');

    cy.makeSnapshot();
  });

  it('changes the "second" label to "seconds" when the value is greater than 1', () => {
    cy.findByTestId('custom').click();
    cy.findByTestId(labelInterval).type('2');
    cy.findAllByText('second').should('not.exist');
    cy.findAllByText('seconds').should('exist');

    cy.makeSnapshot();
  });
});

describe('Global properties', () => {
  it('displays the global properties when the refresh interval is global and interval', () => {
    initializeComponent({
      interval: 50,
      type: 'global'
    });

    cy.contains(`${labelDashboardGlobalInterval} (50 seconds)`).should(
      'be.visible'
    );
  });

  it('displays the global properties when the refresh interval is manual', () => {
    initializeComponent({
      interval: null,
      type: 'manual'
    });

    cy.contains(`${labelDashboardGlobalInterval} (manual)`).should(
      'be.visible'
    );
  });
});
