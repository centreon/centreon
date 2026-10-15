import type { Locator, Page } from '@playwright/test';

import { panelDataTestIds } from '../../www/front_src/src/Configuration/ConfigurationBase/Panel/dataTestIds';
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
} from '../../www/front_src/src/Configuration/Hosts/Specs/utils';
import { expect, type MockApi, type MockedEndpoint } from './fixtures';

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
  details: Record<0 | 1 | 2, MockedEndpoint>;
  duplication: MockedEndpoint;
  listing: MockedEndpoint;
  patch: Record<0 | 1 | 2, MockedEndpoint>;
  resolution: MockedEndpoint;
  /** Selector mocks, by endpoint path (e.g. 'configuration/commands'). */
  selectors: Record<string, MockedEndpoint>;
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
  const details0 = await mockApi({
    endpoint: host(0),
    response: getHostResponse()
  });
  // Any row opens the form, so a row the tests open needs a detail response
  // of its own; host 1 is the one carrying no icon.
  const details1 = await mockApi({
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
  const details2 = await mockApi({
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
  const selectorMocks: Record<string, MockedEndpoint> = {};
  for (const [endpoint, response] of selectors) {
    selectorMocks[endpoint] = await mockApi({ endpoint, response });
  }
  // The macros each template lends; template 7 is unknown to the endpoint.
  for (const [id, response] of Object.entries(getTemplateMacrosResponses())) {
    const endpoint = `configuration/hosts/host_templates/${id}`;
    selectorMocks[endpoint] = await mockApi({ endpoint, response });
  }
  selectorMocks['configuration/hosts/host_templates/7'] = await mockApi({
    endpoint: 'configuration/hosts/host_templates/7',
    response: { code: 404, message: 'Host template not found' },
    status: 404
  });
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
    details: { 0: details0, 1: details1, 2: details2 },
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
    }),
    selectors: selectorMocks
  };
};

// Form fields carry their test id on both the wrapper and the input.
export const hostField = (page: Page, testId: string): Locator =>
  page.locator(
    `:is(input, textarea)[data-testid="${testId}"]:not([type="hidden"])`
  );

type Mount = (id: string, props?: object) => Promise<Locator>;

export interface HostsSetup extends HostsApiOptions {
  isAdditiveInheritanceEnabled?: boolean;
  isCloudPlatform?: boolean;
}

// Mocks the API, mounts the page and waits for the listing.
export const setUpHosts = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  {
    hasWriteAccess = true,
    isAdditiveInheritanceEnabled = false,
    isCloudPlatform = false,
    ...options
  }: HostsSetup = {}
): Promise<{ api: HostsApi; component: Locator }> => {
  const api = await mockHostsApi(mockApi, { hasWriteAccess, ...options });
  const component = await mount(hostsStoryId, {
    hasWriteAccess,
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  await expect(component.getByText('host 0', { exact: true })).toBeVisible();

  return { api, component };
};

export const panelHeader = (page: Page): Locator =>
  page.getByTestId(panelDataTestIds.header);
export const saveButton = (page: Page): Locator =>
  page.locator(`button[data-testid="${panelDataTestIds.save}"]`);
export const openCreation = (component: Locator): Promise<void> =>
  component.getByTestId('add-resource').click();
export const openHost = async (
  component: Locator,
  name: string
): Promise<void> => {
  await component.getByText(name, { exact: true }).click();
  await expect(panelHeader(component.page())).toHaveText(name);
};

// Picks options of an autocomplete; a multi-select stays open after a pick.
export const pickOptions = async (
  page: Page,
  testId: string,
  options: Array<string>
): Promise<void> => {
  await hostField(page, testId).click();
  for (const option of options) {
    await page
      .locator('.MuiAutocomplete-popper')
      .getByText(option, { exact: true })
      .click();
  }
  await page.keyboard.press('Escape');
};

export const fillMandatoryFields = async (page: Page): Promise<void> => {
  await hostField(page, 'host-form-name').fill('srv-apache-02');
  await hostField(page, 'host-form-address').fill('10.0.0.42');
  await pickOptions(page, 'host-form-poller', ['Poller EU']);
};

// Clears an autocomplete through its clear indicator, shown on hover only.
export const clearAutocomplete = (page: Page, testId: string): Promise<void> =>
  page
    .locator('.MuiAutocomplete-root')
    .filter({ has: hostField(page, testId) })
    .locator('.MuiAutocomplete-clearIndicator')
    .click({ force: true });
