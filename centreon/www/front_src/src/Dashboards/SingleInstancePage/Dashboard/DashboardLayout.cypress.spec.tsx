/* eslint-disable import/no-unresolved */

import { createStore, Provider } from 'jotai';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-text/moduleFederation.json'.
import widgetTextConfiguration from 'centreon-widgets/centreon-widget-text/moduleFederation.json';
// @ts-expect-error ts-migrate(2307) FIXME: Cannot find module 'centreon-widgets/centreon-widget-input/moduleFederation.json'.
import widgetInputConfiguration from 'centreon-widgets/centreon-widget-input/moduleFederation.json';
import widgetTextProperties from 'centreon-widgets/centreon-widget-text/properties.json';
import widgetInputProperties from 'centreon-widgets/centreon-widget-input/properties.json';
import widgetGenericTextConfiguration from 'centreon-widgets/centreon-widget-generictext/moduleFederation.json';
import widgetGenericTextProperties from 'centreon-widgets/centreon-widget-generictext/properties.json';
import { BrowserRouter } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import { getDashboardEndpoint } from '../../api/endpoints';

import DashboardLayout from './DashboardLayout';
import { labelEditDashboard } from './translatedLabels';

import { federatedWidgetsPropertiesAtom } from 'www/front_src/src/federatedModules/atoms';

const initializeWidgets = (): ReturnType<typeof createStore> => {
  const federatedWidgets = [
    {
      ...widgetTextConfiguration,
      moduleFederationName: 'centreon-widget-text/src'
    },
    {
      ...widgetInputConfiguration,
      moduleFederationName: 'centreon-widget-input/src'
    },
    {
      ...widgetGenericTextConfiguration,
      moduleFederationName: 'centreon-widget-generictext/src'
    }
  ];

  const store = createStore();
  store.set(federatedWidgetsAtom, federatedWidgets);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetGenericTextProperties
  ]);

  return store;
};

const initialize = (): void => {
  const store = initializeWidgets();

  cy.fixture('Dashboards/Dashboard/details.json').then((dashboardDetails) => {
    cy.interceptAPIRequest({
      alias: 'getDashboardDetails',
      method: Method.GET,
      path: getDashboardEndpoint('1'),
      response: {
        ...dashboardDetails,
        own_role: 'viewer'
      }
    });
  });

  cy.mount({
    Component: (
      <BrowserRouter>
        <TestQueryProvider>
          <Provider store={store}>
            <DashboardLayout displayedDashboardId={1} />
          </Provider>
        </TestQueryProvider>
      </BrowserRouter>
    )
  });
};

describe('DashboardLayout', () => {
  beforeEach(() => {
    initialize();
  });

  it('displays the dashboard from a standalone component', () => {
    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).should('not.exist');

    cy.contains('Widget text').should('be.visible');
    cy.contains('Generic text').should('be.visible');

    cy.makeSnapshot();
  });
});
