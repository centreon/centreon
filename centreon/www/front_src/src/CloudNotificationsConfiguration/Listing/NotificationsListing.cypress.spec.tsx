import { equals } from 'ramda';
import { Provider, createStore } from 'jotai';

import { TestQueryProvider, Method, SnackbarProvider } from '@centreon/ui';

import {
  labelCancel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelFailedToDeleteNotifications,
  labelFailedToDeleteSelectedNotifications,
  labelNotificationSuccessfullyDeleted,
  labelNotificationsSuccessfullyDeleted
} from '../translatedLabels';
import { notificationEndpoint } from '../EditPanel/api/endpoints';
import { DeleteConfirmationDialog } from '../Actions';

import { buildNotificationsEndpoint } from './api/endpoints';
import {
  defaultQueryParams,
  getListingColumns,
  getListingResponse,
  multipleNotificationsSuccessResponse,
  multipleNotificationsWarningResponse,
  multipleNotificationsfailedResponse
} from './testUtils';

import Listing from '.';

const store = createStore();

const ListingWithQueryProvider = (): JSX.Element => {
  return (
    <Provider store={store}>
      <TestQueryProvider>
        <SnackbarProvider>
          <>
            <Listing />
            <DeleteConfirmationDialog />
          </>
        </SnackbarProvider>
      </TestQueryProvider>
    </Provider>
  );
};

const initialize = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  cy.interceptAPIRequest({
    alias: 'secondPageRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      page: 2
    }),
    query: { name: 'page', value: '2' },
    response: getListingResponse({ page: 2 })
  });

  cy.interceptAPIRequest({
    alias: 'lastPageRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      page: 6
    }),
    query: { name: 'page', value: '6' },
    response: getListingResponse({ page: 6 })
  });

  cy.interceptAPIRequest({
    alias: 'listingWithLimit',
    method: Method.GET,
    path: buildNotificationsEndpoint({
      ...defaultQueryParams,
      limit: 20
    }),
    query: { name: 'limit', value: '20' },
    response: getListingResponse({ limit: 20 })
  });

  cy.render(ListingWithQueryProvider);
};

const columnToSort = getListingColumns()
  .filter(({ sortable }) => equals(sortable, true))
  .filter(({ id }) => id !== 'name');

const initializeSorting = (): void => {
  cy.interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: buildNotificationsEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });

  columnToSort.forEach(({ id, label, sortField }) => {
    const sortBy = (sortField || id) as string;

    const requestEndpointDesc = buildNotificationsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'desc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableDesc${label}`,
      method: Method.GET,
      path: requestEndpointDesc,
      response: getListingResponse({})
    });

    const requestEndpointAsc = buildNotificationsEndpoint({
      ...defaultQueryParams,
      sort: {
        [sortBy]: 'asc'
      }
    });

    cy.interceptAPIRequest({
      alias: `dataToListingTableAsc${label}`,
      method: Method.GET,
      path: requestEndpointAsc,
      response: getListingResponse({})
    });
  });

  cy.render(ListingWithQueryProvider);
};

const mockedBulkDelete = (response): void => {
  cy.interceptAPIRequest({
    alias: 'deleteNotificationsRequest',
    method: Method.POST,
    path: `${notificationEndpoint({})}/_delete`,
    response,
    statusCode: 207
  });
};

describe('Notifications Listing', () => {
  beforeEach(initialize);

  it('displays the first page of the notifications listing', () => {
    cy.waitForRequest('@defaultRequest');

    cy.contains('notification1').should('be.visible');

    cy.matchImageSnapshot();
  });

  it('executes a get notifications request after updating limit param', () => {
    cy.waitForRequest('@defaultRequest');

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^20$/).click();

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '20' }],
      requestAlias: 'listingWithLimit'
    });

    cy.get('#Rows\\ per\\ page').click();
    cy.contains(/^10$/).click();

    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '10' }],
      requestAlias: 'defaultRequest'
    });

    cy.contains('notification1').should('be.visible');

    cy.matchImageSnapshot();
  });

  it('executes a listing request with an updated page param when a change page action is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByLabelText('Next page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '2' }],
      requestAlias: 'secondPageRequest'
    });

    cy.findByLabelText('Previous page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.findByLabelText('Last page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '6' }],
      requestAlias: 'lastPageRequest'
    });

    cy.findByLabelText('First page').click();
    cy.waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    cy.contains('notification1').should('be.visible');

    cy.matchImageSnapshot();
  });
});

describe('Listing header: Delete button', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'defaultRequest',
      method: Method.GET,
      path: buildNotificationsEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });
    cy.render(ListingWithQueryProvider);
  });
  it('disables the Delete button when no rows are selected, and displays it when one or more rows are selected', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId('delete multiple notifications').should('be.disabled');
    cy.findByLabelText('Select row 1').click();
    cy.findByLabelText('Select row 2').click();
    cy.findByLabelText('Select row 3').click();
    cy.findByTestId('delete multiple notifications').should('not.be.disabled');

    cy.matchImageSnapshot();
  });

  it('displays a confirmation dialog upon clicking the Delete button', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId('delete multiple notifications').click();

    cy.findByText(labelDeleteNotification);
    cy.findByText(labelDeleteNotificationWarning);
    cy.findByText(labelDelete);
    cy.findByText(labelCancel).click();

    cy.matchImageSnapshot();
  });
  it('displays a success message after a successful deletion', () => {
    mockedBulkDelete(multipleNotificationsSuccessResponse);
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId('delete multiple notifications').click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteNotificationsRequest');
    cy.findByText(labelNotificationsSuccessfullyDeleted);

    cy.waitForRequest('@defaultRequest');

    cy.matchImageSnapshot();
  });
  it('displays a warning message containing the names of the notifications that failed to be deleted if the deletion of some notifications fails', () => {
    mockedBulkDelete(multipleNotificationsWarningResponse);
    cy.waitForRequest('@defaultRequest');

    const warningMessage = `${labelFailedToDeleteNotifications}: notification1, notification2`;

    cy.findByTestId('delete multiple notifications').click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteNotificationsRequest');
    cy.findByText(warningMessage);

    cy.waitForRequest('@defaultRequest');

    cy.matchImageSnapshot();
  });
  it('displays an error message if the deletion of all notifications fails', () => {
    mockedBulkDelete(multipleNotificationsfailedResponse);
    cy.waitForRequest('@defaultRequest');

    cy.findByTestId('delete multiple notifications').click();
    cy.findByText(labelDelete).click();

    cy.waitForRequest('@deleteNotificationsRequest');
    cy.findByText(labelFailedToDeleteSelectedNotifications);

    cy.matchImageSnapshot();
  });
});

describe('Listing row actions: Delete button', () => {
  beforeEach(() => {
    cy.interceptAPIRequest({
      alias: 'defaultRequest',
      method: Method.GET,
      path: buildNotificationsEndpoint(defaultQueryParams),
      response: getListingResponse({})
    });

    cy.interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: undefined,
      statusCode: 204
    });

    cy.render(ListingWithQueryProvider);
  });

  it('displays a confirmation dialog containing the notification name upon clicking the Delete button', () => {
    cy.waitForRequest('@defaultRequest');

    const message = `${labelDelete} « notification1 ».`;
    cy.findAllByTestId(labelDeleteNotification).eq(0).click();
    cy.findByText(message);
    cy.findByText(labelDeleteNotification);
    cy.findByText(labelDeleteNotificationWarning);
    cy.findByText(labelCancel).click();

    cy.matchImageSnapshot();
  });

  it('displays a success message after successful deletion', () => {
    cy.waitForRequest('@defaultRequest');

    cy.findAllByTestId(labelDeleteNotification).eq(0).click();
    cy.findByLabelText(labelDelete).click();

    cy.waitForRequest('@deleteNotificationtRequest');
    cy.waitForRequest('@defaultRequest');

    cy.findByText(labelNotificationSuccessfullyDeleted);

    cy.matchImageSnapshot();
  });

  it('displays an error message upon failed deletion', () => {
    cy.interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: {
        code: 'ok',
        message: 'internal server error'
      },
      statusCode: 500
    });

    cy.waitForRequest('@defaultRequest');

    cy.findAllByTestId(labelDeleteNotification).eq(0).click();
    cy.findByLabelText(labelDelete).click();
    cy.waitForRequest('@deleteNotificationtRequest');

    cy.findByText('internal server error');

    cy.matchImageSnapshot();
  });
});

describe('column sorting', () => {
  beforeEach(initializeSorting);

  it('executes a listing request when a sortable column is clicked', () => {
    cy.waitForRequest('@defaultRequest');

    columnToSort.forEach(({ label, id, sortField }) => {
      const sortBy = (sortField || id) as string;

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `dataToListingTableDesc${label}`
      });

      cy.findByLabelText(`Column ${label}`).click();

      cy.waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `dataToListingTableAsc${label}`
      });

      cy.contains('notification1').should('be.visible');

      cy.matchImageSnapshot(
        `column sorting --  executes a listing request when the ${label} column is clicked`
      );
    });
  });
});
