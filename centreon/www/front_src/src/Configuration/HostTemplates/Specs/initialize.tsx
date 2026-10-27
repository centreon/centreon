import { SnackbarProvider } from '@centreon/ui';
import {
  isAdditiveInheritanceEnabledAtom,
  type PlatformFeatures,
  platformFeaturesAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, interceptRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { HttpResponse } from 'msw';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import {
  getHostTemplateMacrosEndpoint,
  hostFormCommandsEndpoint,
  hostFormContactGroupsEndpoint,
  hostFormContactsEndpoint,
  hostFormHostCategoriesEndpoint,
  hostFormHostSeveritiesEndpoint,
  hostFormHostTemplatesEndpoint,
  hostFormMediasEndpoint,
  hostFormTimePeriodsEndpoint,
  hostTemplateFormServiceTemplatesEndpoint,
  timezonesEndpoint
} from '../../Hosts/api/endpoints';
import {
  getCommandsResponse,
  getContactGroupsResponse,
  getContactsResponse,
  getFormHostTemplatesResponse,
  getHostCategoriesResponse,
  getHostSeveritiesResponse,
  getMediasResponse,
  getTemplateMacrosResponses,
  getTimePeriodsResponse,
  getTimezonesResponse
} from '../../Hosts/Specs/utils';
import HostTemplates from '..';
import {
  getDuplicateHostTemplateEndpoint,
  getHostTemplateEndpoint,
  hostTemplatesListEndpoint
} from '../api/endpoints';
import {
  emptyListingResponse,
  getHostTemplateResponse,
  getListingResponse,
  getServiceTemplatesResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  isCloudPlatform?: boolean;
}

// The templates every PATCH was sent to, in order.
export const patchedTemplateIds: Array<number> = [];

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  isCloudPlatform = false
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven, and the page is not reloaded between tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    configuration_host_template_write: hasWriteAccess
  } as UserPermissions);
  store.set(platformFeaturesAtom, { isCloudPlatform } as PlatformFeatures);
  store.set(isAdditiveInheritanceEnabledAtom, false);

  interceptAPIRequest({
    alias: 'getAllHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  interceptAPIRequest({
    alias: 'getHostTemplate5',
    method: Method.GET,
    path: `**${getHostTemplateEndpoint({ id: 5 })}`,
    response: getHostTemplateResponse()
  });

  interceptAPIRequest({
    alias: 'getHostTemplate8',
    method: Method.GET,
    path: `**${getHostTemplateEndpoint({ id: 8 })}`,
    response: getHostTemplateResponse({ isLocked: true })
  });

  interceptAPIRequest({
    alias: 'createHostTemplate',
    method: Method.POST,
    path: `**${hostTemplatesListEndpoint}`,
    response: { id: 12, name: 'new template' }
  });

  // The host form's selectors, which the template form reads too.
  [
    [
      'getFormHostCategories',
      hostFormHostCategoriesEndpoint,
      getHostCategoriesResponse()
    ],
    ['getFormContacts', hostFormContactsEndpoint, getContactsResponse()],
    [
      'getFormContactGroups',
      hostFormContactGroupsEndpoint,
      getContactGroupsResponse()
    ],
    [
      'getFormTimePeriods',
      hostFormTimePeriodsEndpoint,
      getTimePeriodsResponse()
    ],
    ['getFormTimezones', timezonesEndpoint, getTimezonesResponse()],
    ['getFormCommands', hostFormCommandsEndpoint, getCommandsResponse()],
    [
      'getFormHostSeverities',
      hostFormHostSeveritiesEndpoint,
      getHostSeveritiesResponse()
    ],
    [
      'getFormHostTemplates',
      hostFormHostTemplatesEndpoint,
      getFormHostTemplatesResponse()
    ],
    ['getFormMedias', hostFormMediasEndpoint, getMediasResponse()],
    [
      'getFormServiceTemplates',
      hostTemplateFormServiceTemplatesEndpoint,
      getServiceTemplatesResponse()
    ]
  ].forEach(([alias, endpoint, response]) => {
    interceptAPIRequest({
      alias: alias as string,
      method: Method.GET,
      path: `**${endpoint}?**`,
      response: response as object
    });
  });

  Object.entries(getTemplateMacrosResponses()).forEach(([id, response]) => {
    interceptAPIRequest({
      alias: `getTemplateMacros${id}`,
      method: Method.GET,
      path: `**${getHostTemplateMacrosEndpoint({ id: Number(id) })}`,
      response
    });
  });

  // Edit and mass change are a partial update of the template, one request
  // per template, each one recorded.
  patchedTemplateIds.length = 0;

  [5, 6].forEach((id) => {
    interceptRequest(
      Method.PATCH,
      `**${getHostTemplateEndpoint({ id })}`,
      () => {
        patchedTemplateIds.push(id);

        return HttpResponse.json({}, { status: 200 });
      },
      `patchHostTemplate${id}`
    );
  });

  [5, 6].forEach((id) => {
    interceptAPIRequest({
      alias: `deleteHostTemplate${id}`,
      method: Method.DELETE,
      path: `**${getHostTemplateEndpoint({ id })}`,
      response: {}
    });

    interceptAPIRequest({
      alias: `duplicateHostTemplate${id}`,
      method: Method.POST,
      path: `**${getDuplicateHostTemplateEndpoint({ id })}`,
      response: {}
    });
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
              <HostTemplates />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
