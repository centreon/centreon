import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { createStore, Provider } from 'jotai';
import { useEffect, useMemo } from 'react';

import { DeleteConfirmationDialog } from '../Actions/Delete';
import { DuplicationForm } from '../Actions/Duplicate';
import ResourceAccessRulesListing from './Listing';
import {
  deleteMultipleRulesFailedResponse,
  deleteMultipleRulesSuccessResponse,
  deleteMultipleRulesWarningResponse,
  getListingColumns,
  getListingResponse
} from './Tests/testUtils';

export const Default = (): JSX.Element => {
  const store = useMemo(() => createStore(), []);

  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <>
              <ResourceAccessRulesListing />
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
    failed: deleteMultipleRulesFailedResponse,
    success: deleteMultipleRulesSuccessResponse,
    warning: deleteMultipleRulesWarningResponse
  },
  listing: {
    limit20: getListingResponse({ limit: 20 }),
    page1: getListingResponse({}),
    page2: getListingResponse({ page: 2 }),
    page7: getListingResponse({ page: 7 })
  },
  sortableColumns: getListingColumns()
    .filter(({ id, sortable }) => sortable && id !== 'name')
    .map(({ id, label, sortField }) => ({ label, sortBy: sortField || id }))
};

declare global {
  interface Window {
    resourceAccessRulesListingData?: typeof data;
  }
}

// Data story: the test data module reaches @centreon/ui, which Node cannot
// load, so specs read it from the browser.
export const Data = (): JSX.Element => {
  useEffect(() => {
    window.resourceAccessRulesListingData = data;
  }, []);

  return <p>resource access rules listing data ready</p>;
};
