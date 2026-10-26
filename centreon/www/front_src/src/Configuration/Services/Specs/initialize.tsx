import { SnackbarProvider } from '@centreon/ui';
import {
  intervalLengthAtom,
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

import Services from '..';
import {
  getDuplicateServiceEndpoint,
  getServiceEndpoint,
  servicesListEndpoint,
  serviceTemplatesEndpoint
} from '../api/endpoints';
import {
  emptyListingResponse,
  getListingResponse,
  getServiceTemplatesResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  intervalLength?: number;
}

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  intervalLength = 60
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    configuration_service_write: hasWriteAccess
  } as UserPermissions);
  store.set(intervalLengthAtom, intervalLength);

  interceptAPIRequest({
    alias: 'getAllServices',
    method: Method.GET,
    path: `**${servicesListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  interceptAPIRequest({
    alias: 'getServiceTemplates',
    method: Method.GET,
    path: `**${serviceTemplatesEndpoint}?**`,
    response: getServiceTemplatesResponse()
  });

  // Enable and disable are a partial update of the service, one request each.
  [0, 1, 2].forEach((id) => {
    interceptRequest(
      Method.PATCH,
      `**${getServiceEndpoint({ id })}`,
      () => HttpResponse.json({}),
      `patchService${id}`
    );
  });

  // Every write names one service, so each row has its own intercept.
  [0, 2].forEach((id) => {
    interceptAPIRequest({
      alias: `deleteService${id}`,
      method: Method.DELETE,
      path: `**${getServiceEndpoint({ id })}`,
      response: {}
    });
  });

  interceptAPIRequest({
    alias: 'duplicateService0',
    method: Method.POST,
    path: `**${getDuplicateServiceEndpoint({ id: 0 })}`,
    response: {}
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
              <Services />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
