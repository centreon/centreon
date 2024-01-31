import { ReactElement } from 'react';

import { Provider, createStore } from 'jotai';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { modalStateAtom } from '../../atom';
import { AddEditResourceAccessRuleModal } from '..';
import { ModalMode } from '../../models';
import { resourceAccessRuleEndpoint } from '../api/endpoints';

import { findResourceAccessRuleResponse } from './testUtils';

const store = createStore();
store.set(modalStateAtom, { isOpen: true, mode: ModalMode.Edit });

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
    alias: 'findResourceAccessRuleRequest',
    method: Method.GET,
    path: resourceAccessRuleEndpoint({ id: 1 }),
    response: findResourceAccessRuleResponse()
  });

  cy.interceptAPIRequest({
    alias: 'editResourceAccessRuleRequest',
    method: Method.PUT,
    path: resourceAccessRuleEndpoint({ id: 1 }),
    response: { status: 'ok' }
  });

  cy.mount({
    Component: <ModalWithQueryProvider />
  });
};

describe('Edit modal', () => {
  beforeEach(() => initialize());
});
