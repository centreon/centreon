/* eslint-disable import/no-unresolved */

import { Provider, createStore } from 'jotai';
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import { unstable_Blocker } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';

import { federatedWidgetsAtom } from '../../federatedModules/atoms';
import { labelCancel } from '../translatedLabels';
import { dashboardsEndpoint } from '../api/endpoints';

import { router } from './useDashboardSaveBlocker';
import {
  labelCancelDashboard,
  labelEdit,
  labelYouWillCancelPageWithoutSaving
} from './translatedLabels';
import { routerParams } from './useDashboardDetails';
import { dashboardAtom } from './atoms';

import Dashboard from '.';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);

  return store;
};

const initializeBlocker = (isNavigationBlocked = false): unstable_Blocker => {
  const useBlockerResult: unstable_Blocker = {
    location: {
      hash: '',
      key: '5nvxpbdafa',
      pathname: '/dashboards/1',
      search: '',
      state: null
    },
    proceed: cy.stub(),
    reset: cy.stub(),
    state: isNavigationBlocked ? 'blocked' : 'unblocked'
  };
  cy.stub(router, 'useBlocker').returns(useBlockerResult);

  return useBlockerResult;
};

const initializeAndMount = (): ReturnType<typeof createStore> => {
  const store = initializeWidgets();

  cy.viewport('macbook-13');

  cy.fixture('Dashboards/Dashboard/details.json').then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: `${dashboardsEndpoint}/1`,
      response: dashboardDetails
    });
  });

  cy.fixture('Dashboards/Dashboard/panels.json').then((panels) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardPanels',
      method: Method.GET,
      path: `${dashboardsEndpoint}/1/panels`,
      response: panels
    });
  });

  cy.stub(routerParams, 'useParams').returns({ dashboardId: '1' });

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Dashboard />
        </Provider>
      </TestQueryProvider>
    )
  });

  return store;
};

describe('Dashboard', () => {
  it('cancels the dashboard changes when the "Cancel" button is clicked in the confirmation modal', () => {
    initializeBlocker();
    const store = initializeAndMount();

    cy.waitForRequest('@getDashboardDetails');
    cy.waitForRequest('@getDashboardPanels');

    cy.contains(labelEdit).click();

    cy.fixture('Dashboards/Dashboard/updatedLayout.json').then((panels) => {
      store.set(dashboardAtom, {
        layout: panels
      });
    });

    cy.findByTestId('1_move_panel').should('not.exist');

    cy.contains(labelCancel).click();

    cy.contains(labelCancelDashboard).should('be.visible');
    cy.contains(labelYouWillCancelPageWithoutSaving).should('be.visible');

    cy.matchImageSnapshot();

    cy.findByTestId('cancel_confirmation').click();

    cy.contains(labelEdit).click();

    cy.findByTestId('1_move_panel').should('exist');
  });

  it('closes the cancel confirmation modal when the modal backdrop is clicked', () => {
    initializeBlocker();
    initializeAndMount();

    cy.waitForRequest('@getDashboardDetails');
    cy.waitForRequest('@getDashboardPanels');

    cy.contains(labelEdit).click();

    cy.contains(labelCancel).click();
    cy.get('body').click('topLeft');

    cy.contains(labelCancel).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('displays the cancel confirmation modal when the user tries to navigate away from the dashboard', () => {
    initializeAndMount();

    cy.waitForRequest('@getDashboardDetails');
    cy.waitForRequest('@getDashboardPanels');

    cy.contains(labelEdit).click();

    initializeBlocker(true);

    cy.contains(labelCancelDashboard).should('be.visible');

    cy.matchImageSnapshot();
  });

  it('proceeds the navigation when the corresponding button is clicked and the user tries to navigate away from the dashboard', () => {
    initializeAndMount();

    cy.waitForRequest('@getDashboardDetails');
    cy.waitForRequest('@getDashboardPanels');

    cy.contains(labelEdit).click();

    const { proceed } = initializeBlocker(true);

    cy.contains(labelCancelDashboard).should('be.visible');

    cy.findByTestId('cancel_confirmation')
      .click()
      .then(() => {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        expect(proceed).to.have.been.calledOnce;
      });
  });
});
