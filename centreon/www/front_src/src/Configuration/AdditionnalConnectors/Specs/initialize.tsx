import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { defaultLimitPerPageAtom } from '@centreon/ui-context';

import i18next from 'i18next';
import { getDefaultStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import AdditionnalConnectors from '..';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from '../api';
import { pollersEndpoint } from '../api/endpoints';

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The platform default page size, which listings wait for, in jotai's
  // default store: the page is mounted without a Provider.
  getDefaultStore().set(defaultLimitPerPageAtom, 10);

  cy.fixture('ACC/additionalConnectors.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getConnectors',
      method: Method.GET,
      path: `**${additionalConnectorsEndpoint}?**`,
      response: connectors
    });
  });

  cy.fixture('ACC/additionalConnector.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'getConnector',
      method: Method.GET,
      path: `**${getAdditionalConnectorEndpoint({ id: 1 })}`,
      response: connector
    });

    cy.interceptAPIRequest({
      alias: 'createConnector',
      method: Method.POST,
      path: `**${additionalConnectorsEndpoint}**`,
      response: connector
    });
  });

  cy.fixture('ACC/pollers-vmware.json').then((pollers) => {
    cy.interceptAPIRequest({
      alias: 'geAllowedPollers',
      method: Method.GET,
      path: `**${pollersEndpoint}**`,
      response: pollers
    });
  });

  cy.interceptAPIRequest({
    alias: 'updateConnector',
    method: Method.PUT,
    path: `**${getAdditionalConnectorEndpoint({ id: 1 })}`,
    response: {}
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <div style={{ height: '100vh' }}>
              <AdditionnalConnectors />
            </div>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
