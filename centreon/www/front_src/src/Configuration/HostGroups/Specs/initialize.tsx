import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { BrowserRouter as Router } from 'react-router';
import HostGroups from '..';
import {
  getHostGroupEndpoint,
  hostGroupsListEndpoint,
  hostListEndpoint,
  listImagesEndpoint,
  resourceAccessRulesEndpoint
} from '../api/endpoints';
import {
  getDetailsResponse,
  getListingResponse,
  hostsListEmptyResponse,
  listImagesResponse
} from './utils';

const initialize = ({
  isEmptyHostGroup = false,
  isCloudPlatform = false
}): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform
  });

  cy.interceptAPIRequest({
    alias: 'getAllHostGroups',
    method: Method.GET,
    path: `**${hostGroupsListEndpoint}?**`,
    response: getListingResponse('host group')
  });

  cy.interceptAPIRequest({
    alias: 'getHostGroupDetails',
    method: Method.GET,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: getDetailsResponse({ isCloudPlatform })
  });

  cy.interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**${hostListEndpoint}?**`,
    response: isEmptyHostGroup
      ? hostsListEmptyResponse
      : getListingResponse('host')
  });

  cy.interceptAPIRequest({
    alias: 'getAccessRules',
    method: Method.GET,
    path: `**${resourceAccessRulesEndpoint}?**`,
    response: getListingResponse('rule')
  });

  cy.interceptAPIRequest({
    alias: 'getImagesList',
    method: Method.GET,
    path: `**${listImagesEndpoint}**`,
    response: listImagesResponse
  });

  cy.interceptAPIRequest({
    alias: 'updateHostGroup',
    method: Method.PUT,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: {}
  });

  cy.interceptAPIRequest({
    alias: 'createHostGroup',
    method: Method.POST,
    path: `**${hostGroupsListEndpoint}`,
    response: getDetailsResponse({ isCloudPlatform })
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <HostGroups />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
