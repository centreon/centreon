import { capitalize } from '@mui/material';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { atom, createStore, Provider } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import { FilterConfiguration, ResourceType } from '../../models';
import ConfigurationBase from '..';
import {
  columns,
  columnsAtomKey,
  filtersAtomKey,
  filtersConfiguration,
  filtersInitialValues,
  getEndpoints,
  getListingResponse,
  groups,
  inputs,
  resourceDecoderListDecoder
} from './utils';

export const mockActionsRequests = (resourceType): void => {
  cy.interceptAPIRequest({
    alias: 'deleteOne',
    method: Method.DELETE,
    path: `**${getEndpoints(resourceType).deleteOne({ id: 1 })}`,
    response: { code: 200, status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'delete',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).delete}`,
    response: { code: 200, status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'duplicate',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).duplicate}`,
    response: { code: 200, status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'enable',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).enable?.()}`,
    response: {
      results: [{ href: '/resources/1', message: null, status: 204 }]
    }
  });

  cy.interceptAPIRequest({
    alias: 'disable',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).disable?.()}`,
    response: {
      results: [{ href: '/resources/1', message: null, status: 204 }]
    }
  });
};

const mockListingRequests = (resourceType): void => {
  cy.interceptAPIRequest({
    alias: 'getAll',
    method: Method.GET,
    path: `**${getEndpoints(resourceType).getAll}?**`,
    response: getListingResponse(resourceType)
  });
};

export const mockModalRequests = (resourceType): void => {
  const response = {
    alias: `${resourceType} 1 alias`,
    coordinates: '-20.40,13,12',
    name: `${resourceType} 1`
  };

  cy.interceptAPIRequest({
    alias: 'getDetails',
    method: Method.GET,
    path: `**${getEndpoints(resourceType).getOne?.({ id: 1 })}`,
    response
  });

  cy.interceptAPIRequest({
    alias: 'create',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).create}`,
    response
  });

  cy.interceptAPIRequest({
    alias: 'update',
    method: Method.PUT,
    path: `**${getEndpoints(resourceType).update?.({ id: 1 })}`,
    response: {}
  });
};

const initialize = ({
  resourceType = ResourceType.Host,
  filters = filtersConfiguration
}: {
  resourceType?: ResourceType;
  filters?: Array<FilterConfiguration>;
}): void => {
  const resource = resourceType.replace(' ', '_');

  mockListingRequests(resource);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const selectedColumnIdsAtom = atomWithStorage(columnsAtomKey, []);
  const filtersAtom = atomWithStorage(filtersAtomKey, filtersInitialValues);
  const isWelcomePageDisplayedAtom = atom(false);

  const store = createStore();

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <div style={{ height: '100vh' }}>
                <ConfigurationBase
                  actions={{
                    delete: () => true,
                    duplicate: () => true,
                    edit: true,
                    enableDisable: () => true,
                    massive: true,
                    viewDetails: true
                  }}
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
                  filtersInitialValues={filtersInitialValues}
                  form={{
                    defaultValues: {
                      alias: '',
                      coordinates: '',
                      name: ''
                    },
                    groups,
                    inputs
                  }}
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
                  resourceType={resourceType}
                  selectedColumnIdsAtom={selectedColumnIdsAtom}
                />
              </div>
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
