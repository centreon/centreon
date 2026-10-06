import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import Hosts from '..';
import {
  getDeployServicesEndpoint,
  getDuplicateHostEndpoint,
  getHostEndpoint,
  hostFormContactGroupsEndpoint,
  hostFormContactsEndpoint,
  hostFormHostCategoriesEndpoint,
  hostFormHostGroupsEndpoint,
  hostFormPollersEndpoint,
  hostFormTimePeriodsEndpoint,
  hostGroupsEndpoint,
  hostsListEndpoint,
  hostTemplatesEndpoint,
  pollersEndpoint,
  resolveAddressEndpoint
} from '../api/endpoints';
import {
  emptyListingResponse,
  getContactGroupsResponse,
  getContactsResponse,
  getHostCategoriesResponse,
  getHostGroupsResponse,
  getHostResponse,
  getHostTemplatesResponse,
  getListingResponse,
  getPollersResponse,
  getTimePeriodsResponse,
  refusedAddressResponse,
  resolvedAddressResponse,
  unresolvedAddressResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  isCloudPlatform?: boolean;
  deployFails?: boolean;
  isAdditiveInheritanceEnabled?: boolean;
  addressResolution?: keyof typeof addressResolutions;
  hasDefaultPoller?: boolean;
  pollersDelay?: number;
  resolveDelay?: number;
}

// What the selector answers a user it does not grant.
export const pollersForbiddenMessage = 'You are not allowed to access pollers';

const addressResolutions = {
  refused: { response: refusedAddressResponse, statusCode: 422 },
  resolved: { response: resolvedAddressResponse, statusCode: 200 },
  unresolved: { response: unresolvedAddressResponse, statusCode: 200 }
};

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  isCloudPlatform = false,
  deployFails = false,
  isAdditiveInheritanceEnabled = false,
  addressResolution = 'resolved',
  hasDefaultPoller = true,
  pollersDelay,
  resolveDelay
}: Props): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven and Cypress does not reload between tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  store.set(userPermissionsAtom, {
    configuration_host_write: hasWriteAccess
  });

  store.set(platformFeaturesAtom, { isCloudPlatform });
  store.set(isAdditiveInheritanceEnabledAtom, isAdditiveInheritanceEnabled);

  cy.interceptAPIRequest({
    alias: 'getHost',
    method: Method.GET,
    path: `**${getHostEndpoint({ id: 0 })}`,
    response: getHostResponse()
  });

  // Any row opens the form, so a row the tests open needs a detail response
  // of its own; host 1 is the one carrying no icon.
  cy.interceptAPIRequest({
    alias: 'getHost1',
    method: Method.GET,
    path: `**${getHostEndpoint({ id: 1 })}`,
    response: {
      // A name, so resolving it is not already moot.
      address: 'host-1.example.com',
      categories: [],
      child_hosts: [],
      groups: [],
      name: 'host 1',
      parent_hosts: [],
      poller: { id: 1, name: 'Central' }
    }
  });

  cy.interceptAPIRequest({
    alias: 'createHost',
    method: Method.POST,
    path: `**${hostsListEndpoint}`,
    response: { id: 12, name: 'new host' }
  });

  cy.interceptAPIRequest({
    alias: 'getAllHosts',
    method: Method.GET,
    path: `**${hostsListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  // The form reads its own selectors, granted by host write access; the
  // listing filters below read the generic ones.
  cy.interceptAPIRequest({
    alias: 'getFormPollers',
    delay: pollersDelay,
    method: Method.GET,
    path: `**${hostFormPollersEndpoint}?**`,
    // Granted by write access only, as the API does.
    response: hasWriteAccess
      ? getPollersResponse({ hasDefault: hasDefaultPoller })
      : { code: 403, message: pollersForbiddenMessage },
    statusCode: hasWriteAccess ? 200 : 403
  });

  cy.interceptAPIRequest({
    alias: 'resolveAddress',
    delay: resolveDelay,
    method: Method.GET,
    path: `**${resolveAddressEndpoint}?**`,
    ...addressResolutions[addressResolution]
  });

  cy.interceptAPIRequest({
    alias: 'getFormHostGroups',
    method: Method.GET,
    path: `**${hostFormHostGroupsEndpoint}?**`,
    response: getHostGroupsResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getFormHostCategories',
    method: Method.GET,
    path: `**${hostFormHostCategoriesEndpoint}?**`,
    response: getHostCategoriesResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getFormContacts',
    method: Method.GET,
    path: `**${hostFormContactsEndpoint}?**`,
    response: getContactsResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getFormContactGroups',
    method: Method.GET,
    path: `**${hostFormContactGroupsEndpoint}?**`,
    response: getContactGroupsResponse()
  });

  cy.interceptAPIRequest({
    alias: 'getFormTimePeriods',
    method: Method.GET,
    path: `**${hostFormTimePeriodsEndpoint}?**`,
    response: getTimePeriodsResponse()
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
