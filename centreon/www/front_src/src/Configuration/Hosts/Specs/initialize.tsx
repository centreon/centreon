import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userPermissionsAtom } from '@centreon/ui-context';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import Hosts from '..';
import {
  getDeployServicesEndpoint,
  getDuplicateHostEndpoint,
  getHostEndpoint,
  hostGroupsEndpoint,
  hostsListEndpoint,
  hostTemplatesEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import {
  emptyListingResponse,
  getHostGroupsResponse,
  getHostTemplatesResponse,
  getListingResponse,
  getPollersResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  deployFails?: boolean;
}

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  deployFails = false
}: Props): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(userPermissionsAtom, {
    configuration_host_write: hasWriteAccess
  });

  cy.interceptAPIRequest({
    alias: 'getAllHosts',
    method: Method.GET,
    path: `**${hostsListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getHostGroups',
    method: Method.GET,
    path: `**${hostGroupsEndpoint}?**`,
    response: getHostGroupsResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getPollers',
    method: Method.GET,
    path: `**${pollersEndpoint}?**`,
    response: getPollersResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesEndpoint}?**`,
    response: getHostTemplatesResponse()
  });

  // Enable and disable are a partial update of the host, one request per host.
  cy.interceptAPIRequest({
    alias: 'patchHost',
    method: Method.PATCH,
    path: `**${getHostEndpoint({ id: 0 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'patchHost1',
    method: Method.PATCH,
    path: `**${getHostEndpoint({ id: 1 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'patchHost2',
    method: Method.PATCH,
    path: `**${getHostEndpoint({ id: 2 })}`,
    response: {}
  });

  // Every write names one host, so each row has its own intercept.
  cy.interceptAPIRequest({
    alias: 'deleteHost',
    method: Method.DELETE,
    path: `**${getHostEndpoint({ id: 0 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'deleteHost2',
    method: Method.DELETE,
    path: `**${getHostEndpoint({ id: 2 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'duplicateHost',
    method: Method.POST,
    path: `**${getDuplicateHostEndpoint({ id: 0 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'deployServices',
    method: Method.POST,
    path: `**${getDeployServicesEndpoint({ id: 0 })}`,
    response: deployFails ? { message: 'Host not found' } : {},
    statusCode: deployFails ? 404 : 200
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <div style={{ height: '100vh' }}>
                <Hosts />
              </div>
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
