import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';
import ConfigurationBase from '..';
import { defaultSelectedColumnIds } from '../../HostGroups/utils';
import {
  configurationAtom,
  filtersAtom,
  selectedColumnIdsAtom
} from '../../atoms';
import { FilterConfiguration, ResourceType } from '../../models';
import {
  columns,
  filtersConfiguration,
  filtersInitialValues,
  getEndpoints,
  getListingResponse,
  resourceDecoderListDecoder
} from './utils';

const mockActionsRequests = (resourceType): void => {
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
    response: {
      results: [{ status: 204, message: null, href: '/resources/1' }]
    }
  });

  cy.interceptAPIRequest({
    alias: 'disable',
    method: Method.POST,
    path: `**${getEndpoints(resourceType).disable}`,
    response: {
      results: [{ status: 204, message: null, href: '/resources/1' }]
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

const initialize = ({
  resourceType = ResourceType.Host,
  filters = filtersConfiguration
}: {
  resourceType?: ResourceType;
  filters?: Array<FilterConfiguration>;
}): void => {
  mockListingRequests(resourceType.replace(' ', '_'));

  mockActionsRequests(resourceType.replace(' ', '_'));

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  store.set(filtersAtom, filtersInitialValues);
  store.set(selectedColumnIdsAtom, defaultSelectedColumnIds);

  store.set(configurationAtom, {
    resourceType: resourceType,
    api: {
      endpoints: getEndpoints(resourceType.replace(' ', '_')),
      decoders: { getAll: resourceDecoderListDecoder }
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
                Form={<div />}
              />
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
