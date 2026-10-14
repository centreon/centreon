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
  getTimePeriodsResponse,
  getTimezonesResponse,
  refusedAddressResponse,
  resolvedAddressResponse,
  unresolvedAddressResponse
} from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import type { MockApi, MockedEndpoint } from './fixtures';

// The API mocks of the host configuration page, shared by its specs, as
// Configuration/Hosts/Specs/initialize does for Cypress.
export const hostsStoryId = 'Hosts/Hosts/Default';
export const pollersForbiddenMessage = 'You are not allowed to access pollers';

const addressResolutions = {
  refused: { response: refusedAddressResponse, status: 422 },
  resolved: { response: resolvedAddressResponse, status: 200 },
  unresolved: { response: unresolvedAddressResponse, status: 200 }
};

export interface HostsApiOptions {
  addressResolution?: keyof typeof addressResolutions;
  deployFails?: boolean;
  hasDefaultPoller?: boolean;
  hasWriteAccess?: boolean;
  isEmpty?: boolean;
  pollersDelay?: number;
  resolveDelay?: number;
}

export interface HostsApi {
  creation: MockedEndpoint;
  deletion: Record<0 | 2, MockedEndpoint>;
  deployment: MockedEndpoint;
  duplication: MockedEndpoint;
  listing: MockedEndpoint;
  patch: Record<0 | 1 | 2, MockedEndpoint>;
  resolution: MockedEndpoint;
}

const host = (id: number | string) => `configuration/hosts/${id}`;

export const mockHostsApi = async (
  mockApi: MockApi,
  {
    addressResolution = 'resolved',
    deployFails = false,
    hasDefaultPoller = true,
    hasWriteAccess = true,
    isEmpty = false,
    pollersDelay = 0,
    resolveDelay = 0
  }: HostsApiOptions = {}
): Promise<HostsApi> => {
  await mockApi({ endpoint: host(0), response: getHostResponse() });
  // Any row opens the form, so a row the tests open needs a detail response
  // of its own; host 1 is the one carrying no icon.
  await mockApi({
    endpoint: host(1),
    response: {
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
  await mockApi({
    endpoint: host(2),
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

  const selectors: Array<[string, unknown]> = [
    ['configuration/hosts/host_groups', getHostGroupsResponse()],
    ['configuration/hosts/host_categories', getHostCategoriesResponse()],
    ['configuration/hosts/contacts', getContactsResponse()],
    ['configuration/hosts/contact_groups', getContactGroupsResponse()],
    ['configuration/hosts/timeperiods', getTimePeriodsResponse()],
    ['configuration/hosts/host_severities', getHostSeveritiesResponse()],
    ['configuration/hosts/host_templates', getFormHostTemplatesResponse()],
    ['configuration/hosts/medias', getMediasResponse()],
    ['configuration/timezones', getTimezonesResponse()],
    ['configuration/commands', getCommandsResponse()],
    ['configuration/host_groups', getHostGroupsResponse()],
    ['configuration/pollers', getPollersResponse()],
    ['configuration/host_templates', getHostTemplatesResponse()]
  ];
  for (const [endpoint, response] of selectors) {
    await mockApi({ endpoint, response });
  }
  // Granted by write access only, as the API does.
  await mockApi({
    delay: pollersDelay,
    endpoint: 'configuration/hosts/pollers',
    response: hasWriteAccess
      ? getPollersResponse({ hasDefault: hasDefaultPoller })
      : { code: 403, message: pollersForbiddenMessage },
    status: hasWriteAccess ? 200 : 403
  });

  return {
    creation: await mockApi({
      endpoint: 'configuration/hosts',
      method: 'POST',
      response: { id: 12, name: 'new host' }
    }),
    deletion: {
      0: await mockApi({ endpoint: host(0), method: 'DELETE' }),
      2: await mockApi({ endpoint: host(2), method: 'DELETE' })
    },
    deployment: await mockApi({
      endpoint: `${host(0)}/services/deploy`,
      method: 'POST',
      response: deployFails ? { message: 'Host not found' } : {},
      status: deployFails ? 404 : 200
    }),
    duplication: await mockApi({
      endpoint: `${host(0)}/_duplicate`,
      method: 'POST'
    }),
    listing: await mockApi({
      endpoint: 'configuration/hosts',
      response: isEmpty ? emptyListingResponse : getListingResponse()
    }),
    patch: {
      0: await mockApi({ endpoint: host(0), method: 'PATCH' }),
      1: await mockApi({ endpoint: host(1), method: 'PATCH' }),
      2: await mockApi({ endpoint: host(2), method: 'PATCH' })
    },
    resolution: await mockApi({
      delay: resolveDelay,
      endpoint: 'configuration/hosts/_resolve',
      ...addressResolutions[addressResolution]
    })
  };
};
