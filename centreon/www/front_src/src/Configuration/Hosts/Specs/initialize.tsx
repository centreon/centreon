import { Form, SnackbarProvider } from '@centreon/ui';
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
import { object } from 'yup';

import Hosts from '..';
import {
  getDeployServicesEndpoint,
  getDuplicateHostEndpoint,
  getHostEndpoint,
  getHostTemplateMacrosEndpoint,
  hostFormCommandsEndpoint,
  hostFormContactGroupsEndpoint,
  hostFormContactsEndpoint,
  hostFormHostCategoriesEndpoint,
  hostFormHostGroupsEndpoint,
  hostFormHostSeveritiesEndpoint,
  hostFormHostTemplatesEndpoint,
  hostFormMediasEndpoint,
  hostFormPollersEndpoint,
  hostFormTimePeriodsEndpoint,
  hostGroupsEndpoint,
  hostsListEndpoint,
  hostTemplatesEndpoint,
  pollersEndpoint,
  resolveAddressEndpoint,
  timezonesEndpoint
} from '../api/endpoints';
import {
  getDefaultValues,
  getMassChangeDefaultValues,
  useFormInputs
} from '../Form';
import {
  emptyListingResponse,
  getCommandsResponse,
  getContactGroupsResponse,
  getContactsResponse,
  getFormHostTemplatesResponse,
  getHostCategoriesResponse,
  getHostGroupsResponse,
  getHostResponse,
  getHostSeveritiesResponse,
  getHostTemplatesResponse,
  getListingResponse,
  getMediasResponse,
  getPollersResponse,
  getTemplateMacrosResponses,
  getTimePeriodsResponse,
  getTimezonesResponse,
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
  // The hosts whose partial update is refused.
  patchFails?: Array<number>;
}

// What the selector answers a user it does not grant.
export const pollersForbiddenMessage = 'You are not allowed to access pollers';

export const patchRefusedMessage = 'The host cannot be updated';

// The hosts every PATCH was sent to, in order.
export const patchedHostIds: Array<number> = [];

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
  resolveDelay,
  patchFails = []
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
    configuration_host_write: hasWriteAccess
  } as UserPermissions);

  store.set(platformFeaturesAtom, { isCloudPlatform } as PlatformFeatures);
  store.set(isAdditiveInheritanceEnabledAtom, isAdditiveInheritanceEnabled);

  interceptAPIRequest({
    alias: 'getHost',
    method: Method.GET,
    path: `**${getHostEndpoint({ id: 0 })}`,
    response: getHostResponse()
  });

  // Any row opens the form, so a row the tests open needs a detail response
  // of its own; host 1 is the one carrying no icon.
  interceptAPIRequest({
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

  // As a cloud platform answers: unset values and onPrem-only keys left out.
  interceptAPIRequest({
    alias: 'getHost2',
    method: Method.GET,
    path: `**${getHostEndpoint({ id: 2 })}`,
    response: {
      address: '10.0.0.2',
      categories: [],
      child_hosts: [],
      data_processing: {
        check_freshness: 'false',
        event_handler_enabled: 'true',
        freshness_threshold: 60
      },
      groups: [{ id: 1, name: 'Linux servers' }],
      name: 'host 2',
      parent_hosts: [],
      poller: { id: 1, name: 'Central' }
    }
  });

  interceptAPIRequest({
    alias: 'createHost',
    method: Method.POST,
    path: `**${hostsListEndpoint}`,
    response: { id: 12, name: 'new host' }
  });

  interceptAPIRequest({
    alias: 'getAllHosts',
    method: Method.GET,
    path: `**${hostsListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  // The form reads its own selectors, granted by host write access; the
  // listing filters below read the generic ones.
  interceptAPIRequest({
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

  interceptAPIRequest({
    alias: 'resolveAddress',
    delay: resolveDelay,
    method: Method.GET,
    path: `**${resolveAddressEndpoint}?**`,
    ...addressResolutions[addressResolution]
  });

  interceptAPIRequest({
    alias: 'getFormHostGroups',
    method: Method.GET,
    path: `**${hostFormHostGroupsEndpoint}?**`,
    response: getHostGroupsResponse()
  });

  interceptAPIRequest({
    alias: 'getFormHostCategories',
    method: Method.GET,
    path: `**${hostFormHostCategoriesEndpoint}?**`,
    response: getHostCategoriesResponse()
  });

  interceptAPIRequest({
    alias: 'getFormContacts',
    method: Method.GET,
    path: `**${hostFormContactsEndpoint}?**`,
    response: getContactsResponse()
  });

  interceptAPIRequest({
    alias: 'getFormContactGroups',
    method: Method.GET,
    path: `**${hostFormContactGroupsEndpoint}?**`,
    response: getContactGroupsResponse()
  });

  interceptAPIRequest({
    alias: 'getFormTimePeriods',
    method: Method.GET,
    path: `**${hostFormTimePeriodsEndpoint}?**`,
    response: getTimePeriodsResponse()
  });

  interceptAPIRequest({
    alias: 'getFormTimezones',
    method: Method.GET,
    path: `**${timezonesEndpoint}?**`,
    response: getTimezonesResponse()
  });

  interceptAPIRequest({
    alias: 'getFormCommands',
    method: Method.GET,
    path: `**${hostFormCommandsEndpoint}?**`,
    response: getCommandsResponse()
  });

  interceptAPIRequest({
    alias: 'getFormHostSeverities',
    method: Method.GET,
    path: `**${hostFormHostSeveritiesEndpoint}?**`,
    response: getHostSeveritiesResponse()
  });

  interceptAPIRequest({
    alias: 'getFormHostTemplates',
    method: Method.GET,
    path: `**${hostFormHostTemplatesEndpoint}?**`,
    response: getFormHostTemplatesResponse()
  });

  Object.entries(getTemplateMacrosResponses()).forEach(([id, response]) => {
    interceptAPIRequest({
      alias: `getTemplateMacros${id}`,
      method: Method.GET,
      path: `**${getHostTemplateMacrosEndpoint({ id: Number(id) })}`,
      response
    });
  });

  interceptAPIRequest({
    alias: 'getTemplateMacros7',
    method: Method.GET,
    path: `**${getHostTemplateMacrosEndpoint({ id: 7 })}`,
    response: { code: 404, message: 'Host template not found' },
    statusCode: 404
  });

  interceptAPIRequest({
    alias: 'getFormMedias',
    method: Method.GET,
    path: `**${hostFormMediasEndpoint}?**`,
    response: getMediasResponse()
  });

  interceptAPIRequest({
    alias: 'getHostGroups',
    method: Method.GET,
    path: `**${hostGroupsEndpoint}?**`,
    response: getHostGroupsResponse()
  });

  interceptAPIRequest({
    alias: 'getPollers',
    method: Method.GET,
    path: `**${pollersEndpoint}?**`,
    response: getPollersResponse()
  });

  interceptAPIRequest({
    alias: 'getHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesEndpoint}?**`,
    response: getHostTemplatesResponse()
  });

  // Enable, disable, edit and mass change are a partial update of the host,
  // one request per host. Each one is recorded, so a test can tell exactly
  // which hosts were written to, none included.
  patchedHostIds.length = 0;

  [0, 1, 2].forEach((id) => {
    const fails = patchFails.includes(id);

    interceptRequest(
      Method.PATCH,
      `**${getHostEndpoint({ id })}`,
      () => {
        patchedHostIds.push(id);

        return HttpResponse.json(
          fails ? { code: 422, message: patchRefusedMessage } : {},
          { status: fails ? 422 : 200 }
        );
      },
      id === 0 ? 'patchHost' : `patchHost${id}`
    );
  });

  // Every write names one host, so each row has its own intercept.
  interceptAPIRequest({
    alias: 'deleteHost',
    method: Method.DELETE,
    path: `**${getHostEndpoint({ id: 0 })}`,
    response: {}
  });

  interceptAPIRequest({
    alias: 'deleteHost2',
    method: Method.DELETE,
    path: `**${getHostEndpoint({ id: 2 })}`,
    response: {}
  });

  interceptAPIRequest({
    alias: 'duplicateHost',
    method: Method.POST,
    path: `**${getDuplicateHostEndpoint({ id: 0 })}`,
    response: {}
  });

  interceptAPIRequest({
    alias: 'deployServices',
    method: Method.POST,
    path: `**${getDeployServicesEndpoint({ id: 0 })}`,
    response: deployFails ? { message: 'Host not found' } : {},
    statusCode: deployFails ? 404 : 200
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
              <Hosts />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

interface FormLayoutProps {
  isCloudPlatform?: boolean;
  isMassChange?: boolean;
}

const FormLayout = ({
  isCloudPlatform = false,
  isMassChange = false
}: FormLayoutProps): JSX.Element => {
  const { groups, inputs } = useFormInputs({
    canEdit: true,
    isAdditiveInheritanceEnabled: false,
    isCloudPlatform,
    isMassChange
  });
  const context = { isCloudPlatform, isMassChange };

  return (
    <Form
      areGroupsOpen
      groups={groups}
      initialValues={
        isMassChange
          ? getMassChangeDefaultValues(context)
          : getDefaultValues(context)
      }
      inputs={inputs}
      isCollapsible
      submit={() => undefined}
      validationSchema={object()}
    />
  );
};

// The form alone, laid out against a panel of `width` pixels: the viewport is
// fixed at 1280px, so the widest panel overflows it, and the fields follow the
// panel, as in the page.
export const initializeFormLayout = ({
  width,
  ...props
}: FormLayoutProps & { width: number }): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <SnackbarProvider>
      <QueryClientProvider client={queryClient}>
        {/* The panel content's own classes (`ConfigurationBase/Panel`). */}
        <div
          className="@container px-5"
          data-testid="host-form-layout-panel"
          style={{ width }}
        >
          <FormLayout {...props} />
        </div>
      </QueryClientProvider>
    </SnackbarProvider>
  );
};

export default initialize;
