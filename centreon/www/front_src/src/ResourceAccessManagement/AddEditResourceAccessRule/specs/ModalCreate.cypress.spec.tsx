import { ReactElement } from 'react';

import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { modalWidthStorageAtom } from '../../atom';
import { AddEditResourceAccessRuleModal } from '..';
import {
  findContactGroupsEndpoint,
  findContactsEndpoint,
  findHostCategoriesEndpoint,
  findHostGroupsEndpoint,
  findHostsEndpoint,
  findMetaServicesEndpoint,
  findServiceCategoriesEndpoint,
  findServiceGroupsEndpoint,
  resourceAccessRuleEndpoint
} from '../api/endpoints';

import {
  findContactGroupsResponse,
  findContactsResponse,
  findHostCategoriesResponse,
  findHostGroupsResponse,
  findHostsResponse,
  findMetaServicesResponse,
  findServiceCategoriesResponse,
  findServiceGroupsResponse
} from './testUtils';

const store = createStore();
store.set(modalWidthStorageAtom, 800);

const ModalWithQueryProvider = (): ReactElement => {
  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <AddEditResourceAccessRuleModal />
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'addResourceAccessRuleRequest',
    method: Method.POST,
    path: resourceAccessRuleEndpoint({}),
    response: { status: 'ok' }
  });

  cy.interceptAPIRequest({
    alias: 'findHostGroupsEndpoint',
    method: Method.GET,
    path: `${findHostGroupsEndpoint}**`,
    response: findHostGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findHostCategoriesEndpoint',
    method: Method.GET,
    path: `${findHostCategoriesEndpoint}**`,
    response: findHostCategoriesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findHostsEndpoint',
    method: Method.GET,
    path: `${findHostsEndpoint}**`,
    response: findHostsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findServiceGroupsEndpoint',
    method: Method.GET,
    path: `${findServiceGroupsEndpoint}`,
    response: findServiceGroupsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findServiceCategoriesEndpoint',
    method: Method.GET,
    path: `${findServiceCategoriesEndpoint}**`,
    response: findServiceCategoriesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findMetaServicesEndpoint',
    method: Method.GET,
    path: `${findMetaServicesEndpoint}**`,
    response: findMetaServicesResponse
  });

  cy.interceptAPIRequest({
    alias: 'findContactsEndpoint',
    method: Method.GET,
    path: `${findContactsEndpoint}**`,
    response: findContactsResponse
  });

  cy.interceptAPIRequest({
    alias: 'findContactGroupsEndpoint',
    method: Method.GET,
    path: `${findContactGroupsEndpoint}**`,
    response: findContactGroupsResponse
  });

  cy.viewport('macbook-13');

  cy.mount({
    Component: <ModalWithQueryProvider />
  });
};
