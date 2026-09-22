import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import Hosts from '..';
import { hostsListEndpoint } from '../api/endpoints';
import { emptyListingResponse, getListingResponse } from './utils';

interface Props {
  isEmpty?: boolean;
}

const initialize = ({ isEmpty = false }: Props): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  cy.interceptAPIRequest({
    alias: 'getAllHosts',
    method: Method.GET,
    path: `**${hostsListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <div style={{ height: '100vh' }}>
                <Hosts />
              </div>
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
