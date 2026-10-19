import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { createStore, Provider } from 'jotai';
import { useEffect, useMemo } from 'react';

import { DeleteConfirmationDialog } from '../Actions/Delete';
import { DuplicationForm } from '../Actions/Duplicate';
import { getNotificationResponse } from '../Panel/specs/testUtils';
import Listing from '.';
import {
  getListingColumns,
  getListingResponse,
  multipleNotificationsfailedResponse,
  multipleNotificationsSuccessResponse,
  multipleNotificationsWarningResponse
} from './Tests/testUtils';

export const Default = (): JSX.Element => {
  const store = useMemo(() => createStore(), []);

  return (
    <div style={{ height: '90vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <>
              <Listing />
              <DeleteConfirmationDialog />
              <DuplicationForm />
            </>
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

const data = {
  bulkDeletion: {
    failed: multipleNotificationsfailedResponse,
    success: multipleNotificationsSuccessResponse,
    warning: multipleNotificationsWarningResponse
  },
  listing: {
    limit20: getListingResponse({ limit: 20 }),
    page1: getListingResponse({}),
    page2: getListingResponse({ page: 2 }),
    page6: getListingResponse({ page: 6 })
  },
  notification: getNotificationResponse({}),
  sortableColumns: getListingColumns()
    .filter(({ id, sortable }) => sortable && id !== 'name')
    .map(({ id, label, sortField }) => ({ label, sortBy: sortField || id }))
};

declare global {
  interface Window {
    notificationsListingData?: typeof data;
  }
}

// Data story: the test data module reaches @centreon/ui, which Node cannot
// load, so specs read it from the browser.
export const Data = (): JSX.Element => {
  useEffect(() => {
    window.notificationsListingData = data;
  }, []);

  return <p>notifications listing data ready</p>;
};
