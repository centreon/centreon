import { capitalize } from '@mui/material';

import { SnackbarProvider } from '@centreon/ui';

import { rs } from '@rstest/core';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { atom, createStore, Provider } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import {
  type Actions,
  type FilterConfiguration,
  type Form,
  ResourceType
} from '../../models';
import ConfigurationBase from '..';
import { panelWidthAtom } from '../atoms';
import {
  columns,
  columnsAtomKey,
  filtersAtomKey,
  filtersConfiguration,
  filtersInitialValues,
  getEndpoints,
  getHostTemplatesResponse,
  getListingResponse,
  groups,
  hostTemplatesEndpoint,
  inputs,
  resourceDecoderListDecoder
} from './utils';

// The resource as the endpoints and the fixtures name it: `host group` is
// served under `/configuration/host_group`, its rows are `host_group 1`...
export const toResource = (resourceType: string): string =>
  resourceType.replace(' ', '_');

// What the detail endpoint answers for the resource with id 1.
export const getDetailsResponse = (
  resource: string
): Record<string, string> => ({
  alias: `${resource} 1 alias`,
  coordinates: '-20.40,13,12',
  name: `${resource} 1`
});

// `disableFails`: the disable request is refused, answered after a delay so a
// test can see the toggle flip before it is put back.
export const mockActionsRequests = (
  resource: string,
  disableFails = false
): void => {
  interceptAPIRequest({
    alias: 'deleteOne',
    method: Method.DELETE,
    path: `**${getEndpoints(resource).deleteOne?.({ id: 1 })}`,
    response: { code: 200, status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'delete',
    method: Method.POST,
    path: `**${getEndpoints(resource).delete}`,
    response: { code: 200, status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'duplicate',
    method: Method.POST,
    path: `**${getEndpoints(resource).duplicate}`,
    response: { code: 200, status: 'ok' }
  });

  interceptAPIRequest({
    alias: 'enable',
    method: Method.POST,
    path: `**${getEndpoints(resource).enable?.({ id: 1 })}`,
    response: {
      results: [{ href: '/resources/1', message: null, status: 204 }]
    }
  });

  interceptAPIRequest({
    alias: 'disable',
    delay: disableFails ? 500 : 0,
    method: Method.POST,
    path: `**${getEndpoints(resource).disable?.({ id: 1 })}`,
    response: {
      results: [
        {
          href: '/resources/1',
          message: disableFails ? 'Resource in use' : null,
          status: disableFails ? 409 : 204
        }
      ]
    }
  });
};

const mockListingRequests = (resource: string): void => {
  interceptAPIRequest({
    alias: 'getAll',
    method: Method.GET,
    path: `**${getEndpoints(resource).getAll}?**`,
    response: getListingResponse(resource)
  });

  interceptAPIRequest({
    alias: 'getHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesEndpoint}**`,
    response: getHostTemplatesResponse()
  });
};

export const mockModalRequests = (resource: string): void => {
  const response = getDetailsResponse(resource);

  interceptAPIRequest({
    alias: 'getDetails',
    method: Method.GET,
    path: `**${getEndpoints(resource).getOne?.({ id: 1 })}`,
    response
  });

  interceptAPIRequest({
    alias: 'create',
    method: Method.POST,
    path: `**${getEndpoints(resource).create}`,
    response
  });

  interceptAPIRequest({
    alias: 'update',
    method: Method.PUT,
    path: `**${getEndpoints(resource).update?.({ id: 1 })}`,
    response: {}
  });
};

const defaultActions: Actions = {
  delete: () => true,
  duplicate: () => true,
  edit: true,
  enableDisable: () => true,
  massive: true,
  viewDetails: true
};

interface ScreenSize {
  height: number;
  width: number;
}

interface Props {
  resourceType?: ResourceType | string;
  filters?: Array<FilterConfiguration>;
  initialValues?: Record<string, unknown>;
  filtersPanelWidth?: number;
  actions?: Actions;
  formVariant?: 'modal' | 'panel';
  formPanelWidth?: number;
  panelWidth?: number;
  searchParams?: string;
  // The screen the page is shown on, for the tests Cypress ran under
  // `cy.viewport`. The rstest viewport cannot be resized from a test, so the
  // page is given that size and `window.innerWidth` reports that width.
  screenSize?: ScreenSize;
}

const initialize = ({
  resourceType = ResourceType.Host,
  filters = filtersConfiguration,
  initialValues = filtersInitialValues,
  filtersPanelWidth,
  actions = defaultActions,
  formVariant,
  formPanelWidth,
  panelWidth,
  searchParams = '',
  screenSize
}: Props = {}): RenderResult => {
  const resource = toResource(resourceType);

  // A deep link is the URL the page opens on. Always set, so no test leaks
  // its URL into the next.
  window.history.pushState({}, '', searchParams || window.location.pathname);

  if (screenSize) {
    // Restored after the test (`restoreMocks`).
    rs.spyOn(window, 'innerWidth', 'get').mockReturnValue(screenSize.width);
  }

  mockListingRequests(resource);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const selectedColumnIdsAtom = atomWithStorage<Array<string>>(
    columnsAtomKey,
    []
  );
  const filtersAtom = atomWithStorage(filtersAtomKey, initialValues);
  const isWelcomePageDisplayedAtom = atom(false);

  const store = createStore();

  // Always set: the store is new for each test but localStorage is not, so a
  // width one test drags to would otherwise open the next one.
  store.set(panelWidthAtom, panelWidth ?? null);

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={store}>
            <div
              style={
                screenSize
                  ? { height: screenSize.height, width: screenSize.width }
                  : { height: '100vh' }
              }
            >
              <ConfigurationBase
                actions={actions}
                api={{
                  adapter: (data) => data,
                  decoders: { getAll: resourceDecoderListDecoder },
                  endpoints: getEndpoints(resource)
                }}
                columns={columns}
                columnsAtomKey={columnsAtomKey}
                defaultSelectedColumnIds={[
                  'name',
                  'alias',
                  'actions',
                  'is_activated'
                ]}
                filtersAtom={filtersAtom}
                filtersAtomKey={filtersAtomKey}
                filtersConfiguration={filters}
                filtersInitialValues={initialValues}
                filtersPanelWidth={filtersPanelWidth}
                // No validation schema: the form accepts anything.
                form={
                  {
                    defaultValues: {
                      alias: '',
                      coordinates: '',
                      name: ''
                    },
                    groups,
                    inputs
                  } as Form
                }
                formPanelWidth={formPanelWidth}
                formVariant={formVariant}
                isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
                labels={{
                  title: `${capitalize(resourceType)}s`,
                  welcomePage: {
                    actions: {
                      create: 'Add configuration base'
                    },
                    title: 'Welcome to configuration base'
                  }
                }}
                resourceType={resourceType as ResourceType}
                selectedColumnIdsAtom={selectedColumnIdsAtom}
              />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
