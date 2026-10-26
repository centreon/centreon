import { SnackbarProvider } from '@centreon/ui';
import {
  type PlatformFeatures,
  platformFeaturesAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
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

interface Props {
  isEmptyHostGroup?: boolean;
  isCloudPlatform?: boolean;
  hasWriteAccess?: boolean;
}

const initialize = ({
  isEmptyHostGroup = false,
  isCloudPlatform = false,
  hasWriteAccess = true
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form state follows the URL, and the page is not reloaded between
  // tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform
  } as unknown as PlatformFeatures);

  store.set(userPermissionsAtom, {
    configuration_host_group_write: hasWriteAccess
  } as UserPermissions);

  interceptAPIRequest({
    alias: 'getAllHostGroups',
    method: Method.GET,
    path: `**${hostGroupsListEndpoint}?**`,
    response: getListingResponse('host group')
  });

  interceptAPIRequest({
    alias: 'getHostGroupDetails',
    method: Method.GET,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: getDetailsResponse({ isCloudPlatform })
  });

  interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**${hostListEndpoint}?**`,
    response: isEmptyHostGroup
      ? hostsListEmptyResponse
      : getListingResponse('host')
  });

  interceptAPIRequest({
    alias: 'getAccessRules',
    method: Method.GET,
    path: `**${resourceAccessRulesEndpoint}?**`,
    response: getListingResponse('rule')
  });

  interceptAPIRequest({
    alias: 'getImagesList',
    method: Method.GET,
    path: `**${listImagesEndpoint}**`,
    response: listImagesResponse
  });

  interceptAPIRequest({
    alias: 'updateHostGroup',
    method: Method.PUT,
    path: `**${getHostGroupEndpoint({ id: 1 })}`,
    response: {}
  });

  interceptAPIRequest({
    alias: 'createHostGroup',
    method: Method.POST,
    path: `**${hostGroupsListEndpoint}`,
    response: getDetailsResponse({ isCloudPlatform })
  });

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <HostGroups />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
