import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';
import ConfigurationBase from '..';
import { FilterConfiguration, ResourceType } from '../../models';
import { configurationAtom, filtersAtom } from '../atoms';
import {
  columns,
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
    response: { status: 'ok', code: 200 }
  });

  cy.interceptAPIRequest({
    alias: 'delete',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).delete}`,
    response: { status: 'ok', code: 200 }
  });

  cy.interceptAPIRequest({
    alias: 'duplicate',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).duplicate}`,
    response: { status: 'ok', code: 200 }
  });

  cy.interceptAPIRequest({
    alias: 'enable',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).enable}`,
    response: { status: 'ok', code: 200 }
  });

  cy.interceptAPIRequest({
    alias: 'disable',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).disable}`,
    response: { status: 'ok', code: 200 }
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
    name: `${resourceType} 1`,
    alias: `${resourceType} 1 alias`,
    coordinates: '-20.40,13,12'
  };

  cy.interceptAPIRequest({
    alias: 'getDetails',
    method: Method.GET,
    path: `**${getEndpoints(resourceType).getOne({ id: 1 })}`,
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
    path: `**${getEndpoints(resourceType).update({ id: 1 })}`,
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

  const store = createStore();
  store.set(filtersAtom, filtersInitialValues);

  store.set(configurationAtom, {
    resourceType: resourceType,
    api: {
      endpoints: getEndpoints(resource),
      decoders: { getAll: resourceDecoderListDecoder },
      adapter: (data) => data
    },
    filtersConfiguration: filters,
    filtersInitialValues,
    defaultSelectedColumnIds: ['name', 'alias', 'actions', 'is_activated']
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <ConfigurationBase
                resourceType={resourceType}
                columns={columns}
                form={{
                  groups,
                  inputs,
                  defaultValues: {
                    name: '',
                    alias: '',
                    coordinates: ''
                  }
                }}
              />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
