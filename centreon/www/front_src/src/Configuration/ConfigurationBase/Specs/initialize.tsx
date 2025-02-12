import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { BrowserRouter as Router } from 'react-router';
import ConfigurationBase from '..';
import { configurationAtom, filtersAtom } from '../../atoms';
import { FilterConfiguration, ResourceType } from '../../models';
import {
  columns,
  filtersConfiguration,
  filtersInitialValues,
  getEndpoints,
  getListingResponse,
  resourceDecoderListDecoder
} from './utils';

const mockRequests = (resourceType): void => {
  cy.interceptAPIRequest({
    alias: 'getAll',
    method: Method.GET,
    path: `**${getEndpoints(resourceType).getAll}?**`,
    response: getListingResponse(resourceType)
  });
};

const store = createStore();

const initialize = ({
  resourceType = ResourceType.Host,
  filters = filtersConfiguration
}: {
  resourceType?: ResourceType;
  filters?: Array<FilterConfiguration>;
}): void => {
  mockRequests(resourceType.replace(' ', '_'));

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

  store.set(filtersAtom, filtersInitialValues);

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
