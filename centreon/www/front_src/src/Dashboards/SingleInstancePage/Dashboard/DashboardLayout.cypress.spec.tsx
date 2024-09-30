import widgetGenericTextProperties from './Widgets/centreon-widget-generictext/properties.json';
import widgetInputProperties from './Widgets/centreon-widget-input/properties.json';
import widgetTextProperties from './Widgets/centreon-widget-text/properties.json';

import { Provider, createStore } from 'jotai';
import { BrowserRouter } from 'react-router-dom';

import { Method, TestQueryProvider } from '@centreon/ui';
import { federatedWidgetsAtom, isOnPublicPageAtom } from '@centreon/ui-context';

import {
  getDashboardEndpoint,
  getPublicDashboardEndpoint
} from '../../api/endpoints';

import DashboardLayout from './DashboardLayout';
import { labelEditDashboard } from './translatedLabels';

import { federatedWidgetsPropertiesAtom } from 'www/front_src/src/federatedModules/atoms';
import { internalWidgetComponents } from './Widgets/widgets';

const initializeWidgets = (): ReturnType<typeof createStore> => {

  const store = createStore();
  store.set(federatedWidgetsAtom, internalWidgetComponents);
  store.set(federatedWidgetsPropertiesAtom, [
    widgetTextProperties,
    widgetInputProperties,
    widgetGenericTextProperties
  ]);

  return store;
};

const initialize = (isPublic = false): void => {
  const store = initializeWidgets();

  store.set(isOnPublicPageAtom, isPublic);

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

    cy.interceptAPIRequest({
      alias: 'getPublicDashboardDetails',
      method: Method.GET,
      path: `./api/latest${getPublicDashboardEndpoint({ dashboardId: '1', playlistID: 'hash' })}`,
      response: dashboardDetails
    });
  });

  cy.mount({
    Component: (
      <BrowserRouter>
        <TestQueryProvider>
          <Provider store={store}>
            <DashboardLayout
              displayedDashboardId={1}
              playlistHash={isPublic ? 'hash' : undefined}
            />
          </Provider>
        </TestQueryProvider>
      </BrowserRouter>
    )
  });
};

describe('DashboardLayout', () => {
  it('displays the dashboard from a standalone component', () => {
    initialize();

    cy.waitForRequest('@getDashboardDetails');

    cy.contains(labelEditDashboard).should('not.exist');

    cy.contains('Widget text').should('be.visible');
    cy.contains('Generic text').should('be.visible');

    cy.makeSnapshot();
  });

  it('sends a request to the public API when the dashboard is displayed in a public page', () => {
    initialize(true);
    cy.waitForRequest('@getPublicDashboardDetails');
  });
});
