import { type ListingParameters, SnackbarProvider } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { equals } from 'ramda';
import { initReactI18next } from 'react-i18next';

import { DeleteConfirmationDialog } from '../../Actions/Delete';
import { DuplicationForm } from '../../Actions/Duplicate';
import { notificationEndpoint } from '../../Panel/api/endpoints';
import { getNotificationResponse } from '../../Panel/specs/testUtils';
import {
  labelActions,
  labelCancel,
  labelChannels,
  labelDelete,
  labelDeleteMultipleNotifications,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelDiscard,
  labelDuplicate,
  labelFailedToDeleteNotifications,
  labelFailedToDeleteSelectedNotifications,
  labelName,
  labelNotificationDuplicated,
  labelNotificationName,
  labelNotificationSuccessfullyDeleted,
  labelNotificationsSuccessfullyDeleted,
  labelPeriod,
  labelPleaseEnterNameForDuplicatedNotification,
  labelRequired,
  labelResources,
  labelStatus,
  labelThisNameAlreadyExists,
  labelUsers
} from '../../translatedLabels';
import Listing from '..';
import { buildNotificationsEndpoint } from '../api/endpoints';
import {
  defaultQueryParams,
  getListingColumns,
  getListingResponse,
  multipleNotificationsfailedResponse,
  multipleNotificationsSuccessResponse,
  multipleNotificationsWarningResponse
} from './testUtils';

// The endpoint the page builds for these parameters; the fixture leaves out
// what the page does not set.
const getListingEndpoint = (parameters: object): string =>
  buildNotificationsEndpoint(parameters as ListingParameters);

const notificationNames = Array.from(
  { length: 10 },
  (_, index) => `notification${index + 1}`
);

const renderListing = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // A store and a client of their own, so no selection, page or cache leaks
  // from one test to the next.
  const store = createStore();
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <Provider store={store}>
      <QueryClientProvider client={queryClient}>
        <SnackbarProvider>
          <div style={{ height: '90vh' }}>
            <Listing />
            <DeleteConfirmationDialog />
            <DuplicationForm />
          </div>
        </SnackbarProvider>
      </QueryClientProvider>
    </Provider>
  );
};

const interceptDefaultListing = (): void => {
  interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: getListingEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });
};

const initialize = (): void => {
  interceptDefaultListing();

  interceptAPIRequest({
    alias: 'secondPageRequest',
    method: Method.GET,
    path: getListingEndpoint({
      ...defaultQueryParams,
      page: 2
    }),
    query: { name: 'page', value: '2' },
    response: getListingResponse({ page: 2 })
  });

  interceptAPIRequest({
    alias: 'lastPageRequest',
    method: Method.GET,
    path: getListingEndpoint({
      ...defaultQueryParams,
      page: 6
    }),
    query: { name: 'page', value: '6' },
    response: getListingResponse({ page: 6 })
  });

  interceptAPIRequest({
    alias: 'listingWithLimit',
    method: Method.GET,
    path: getListingEndpoint({
      ...defaultQueryParams,
      limit: 20
    }),
    query: { name: 'limit', value: '20' },
    response: getListingResponse({ limit: 20 })
  });

  renderListing();
};

const columnToSort = getListingColumns()
  .filter(({ sortable }) => equals(sortable, true))
  .filter(({ id }) => id !== 'name');

const getSortBy = ({
  id,
  sortField
}: {
  id: string;
  sortField?: string;
}): string => sortField || id;

const initializeSorting = (): void => {
  interceptDefaultListing();

  // All the listing requests share one path: each sort has its own mock,
  // told apart by its `sort_by` parameter.
  columnToSort.forEach(({ id, label, sortField }) => {
    const sortBy = getSortBy({ id, sortField });

    ['desc', 'asc'].forEach((order) => {
      const sort = { [sortBy]: order };

      interceptAPIRequest({
        alias: `dataToListingTable${equals(order, 'desc') ? 'Desc' : 'Asc'}${label}`,
        method: Method.GET,
        path: getListingEndpoint({ ...defaultQueryParams, sort }),
        query: { name: 'sort_by', value: JSON.stringify(sort) },
        response: getListingResponse({})
      });
    });
  });

  renderListing();
};

const mockedBulkDelete = (response: object): void => {
  interceptAPIRequest({
    alias: 'deleteNotificationsRequest',
    method: Method.POST,
    path: `${notificationEndpoint({})}/_delete`,
    response,
    statusCode: 200
  });
};

interface Query {
  key: string;
  value: string | object;
}

const waitForRequestAndVerifyQueries = async ({
  queries,
  requestAlias
}: {
  queries: Array<Query>;
  requestAlias: string;
}): Promise<void> => {
  const { request } = await waitForRequest(`@${requestAlias}`);

  queries.forEach(({ key, value }) => {
    expect(request.url.searchParams.get(key)).toBe(
      typeof value === 'string' ? value : JSON.stringify(value)
    );
  });
};

const getRowsPerPage = (): HTMLElement => {
  const rowsPerPage = document.querySelector<HTMLElement>('#Rows\\ per\\ page');

  expect(rowsPerPage).not.toBeNull();

  return rowsPerPage as HTMLElement;
};

// The select shows the limit once the listing applied it: choosing a value
// while it still shows that value would change nothing.
const selectRowsPerPage = async (value: string): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });
  await userEvent.click(getRowsPerPage());
  await userEvent.click(
    within(await screen.findByRole('listbox')).getByRole('option', {
      name: value
    })
  );
  await waitFor(() => {
    expect(getRowsPerPage()).toHaveTextContent(value);
  });
};

// The notification rows, in the order they are displayed.
const getBodyRows = (): Array<HTMLElement> =>
  screen
    .getAllByRole('row')
    .filter((row) => within(row).queryByLabelText(/^Select row/) !== null);

// Previous and First are disabled on the first page, Next and Last on the
// last one: wait for the page change to be applied before the next click.
const clickPaginationButton = async (label: string): Promise<void> => {
  const button = await screen.findByLabelText(label);

  await waitFor(() => expect(button).toBeEnabled());
  await userEvent.click(button);
};

const expectFirstPage = async (): Promise<void> => {
  expect(await screen.findByText('notification1')).toBeVisible();
  expect(await screen.findByText('1-10 of 56')).toBeVisible();
  expect(getRowsPerPage()).toHaveTextContent(/^10$/);
};

const getDeleteMultipleButton = (): HTMLElement =>
  screen.getByTestId(labelDeleteMultipleNotifications);

// Whether a row shows as selected. The listing cancels the native click on
// its checkboxes and renders their state from its own selection, so the
// input's `checked` property is not reliable: the icon drawn is.
const isRowShownSelected = (index: number): boolean => {
  const checkbox = screen.getByLabelText(`Select row ${index}`);

  return (
    checkbox.parentElement?.querySelector('[data-testid="CheckBoxIcon"]') !==
    null
  );
};

const selectFirstThreeRows = async (): Promise<void> => {
  await userEvent.click(await screen.findByLabelText('Select row 1'));
  await userEvent.click(screen.getByLabelText('Select row 2'));
  await userEvent.click(screen.getByLabelText('Select row 3'));
};

// Once a bulk deletion settled, the selection is emptied.
const expectNoRowSelected = async (): Promise<void> => {
  await waitFor(() => {
    expect(getDeleteMultipleButton()).toBeDisabled();
    notificationNames.forEach((_, index) => {
      expect(isRowShownSelected(index + 1)).toBe(false);
    });
  });
};

const openDuplicateDialog = async (): Promise<HTMLElement> => {
  await userEvent.click((await screen.findAllByTestId(labelDuplicate))[0]);

  return screen.findByRole('dialog');
};

const getNameField = (): HTMLElement =>
  screen.getByLabelText(labelNotificationName);

const discardDuplication = async (): Promise<void> => {
  await userEvent.click(screen.getByText(labelDiscard));

  await waitFor(() =>
    expect(screen.queryByText(labelDiscard)).not.toBeInTheDocument()
  );
};

describe('Notifications Listing', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize();
  });

  it('displays the first page of the notifications listing', async () => {
    await waitForRequest('@defaultRequest');

    expect(await screen.findByText('notification1')).toBeVisible();

    const columnLabels = [
      labelName,
      labelUsers,
      labelResources,
      labelPeriod,
      labelChannels,
      labelActions,
      labelStatus
    ];

    // Every column, in this order, after the selection column.
    expect(
      Array.from(document.querySelectorAll('[scope="col"]')).map(
        (header) => header.textContent
      )
    ).toEqual(['', ...columnLabels]);

    // Sorted by name, ascending; no other column is the active sort.
    const nameSort = screen.getByLabelText(`Column ${labelName}`);

    expect(nameSort).toHaveClass('Mui-active');
    expect(within(nameSort).getByTestId('ArrowDownwardIcon')).toHaveClass(
      'MuiTableSortLabel-iconDirectionAsc'
    );
    [labelUsers, labelResources, labelStatus].forEach((label) => {
      expect(screen.getByLabelText(`Column ${label}`)).not.toHaveClass(
        'Mui-active'
      );
    });

    const rows = getBodyRows();

    expect(rows).toHaveLength(notificationNames.length);

    // Each row: its name, users, resources by type, period, the email
    // channel, its actions, and its status (every second one activated).
    rows.forEach((row, index) => {
      expect(row).toHaveTextContent(notificationNames[index]);
      expect(row).toHaveTextContent(/\d+ users/);
      expect(row).toHaveTextContent(/\d+ SG, \d+ HG, \d+ BV/);
      expect(row).toHaveTextContent('24h/24 - 7/7 days');
      expect(
        within(row).getByTestId('LocalPostOfficeOutlinedIcon')
      ).toBeInTheDocument();
      expect(within(row).getByTestId(labelDeleteNotification)).toBeEnabled();
      expect(within(row).getByTestId(labelDuplicate)).toBeEnabled();

      const checkboxes = within(row).getAllByRole('checkbox');
      const status = checkboxes[checkboxes.length - 1];

      if (index % 2) {
        expect(status).toBeChecked();
      } else {
        expect(status).not.toBeChecked();
      }
    });

    expect(screen.getByText('1-10 of 56')).toBeVisible();
    expect(screen.getByTestId('createNotification')).toBeEnabled();
    expect(getDeleteMultipleButton()).toBeDisabled();
  });

  it('executes a get notifications request after updating limit param', async () => {
    await waitForRequest('@defaultRequest');

    await selectRowsPerPage('20');

    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '20' }],
      requestAlias: 'listingWithLimit'
    });

    await selectRowsPerPage('10');

    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'limit', value: '10' }],
      requestAlias: 'defaultRequest'
    });

    await expectFirstPage();
  });

  it('executes a listing request with an updated page param when a change page action is clicked', async () => {
    await waitForRequest('@defaultRequest');

    await clickPaginationButton('Next page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '2' }],
      requestAlias: 'secondPageRequest'
    });

    await clickPaginationButton('Previous page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    await clickPaginationButton('Last page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '6' }],
      requestAlias: 'lastPageRequest'
    });

    await clickPaginationButton('First page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    await expectFirstPage();
  });
});

describe('Listing header: Delete button', () => {
  beforeEach(() => {
    expect.hasAssertions();
    interceptDefaultListing();
    renderListing();
  });

  it('disables the Delete button when no rows are selected, and displays it when one or more rows are selected', async () => {
    await waitForRequest('@defaultRequest');

    expect(
      await screen.findByTestId(labelDeleteMultipleNotifications)
    ).toBeDisabled();
    await userEvent.click(await screen.findByLabelText('Select row 1'));
    await userEvent.click(screen.getByLabelText('Select row 2'));
    await userEvent.click(screen.getByLabelText('Select row 3'));
    await waitFor(() => expect(getDeleteMultipleButton()).toBeEnabled());

    // Only the three clicked rows are selected.
    await waitFor(() => {
      notificationNames.forEach((_, index) => {
        expect(isRowShownSelected(index + 1)).toBe(index < 3);
      });
    });
  });

  it('displays a confirmation dialog upon clicking the Delete button', async () => {
    await waitForRequest('@defaultRequest');

    // The button is only enabled with a selection.
    await userEvent.click(await screen.findByLabelText('Select row 1'));
    await waitFor(() => expect(getDeleteMultipleButton()).toBeEnabled());

    await userEvent.click(getDeleteMultipleButton());

    expect(await screen.findByText(labelDeleteNotification)).toBeVisible();
    expect(screen.getByText(labelDeleteNotificationWarning)).toBeVisible();
    expect(screen.getByText(labelDelete)).toBeVisible();
    await userEvent.click(screen.getByText(labelCancel));

    // Cancelling closes the dialog and keeps the selection.
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
    expect(isRowShownSelected(1)).toBe(true);
    expect(getDeleteMultipleButton()).toBeEnabled();
  });

  it('displays a success message after a successful deletion', async () => {
    mockedBulkDelete(multipleNotificationsSuccessResponse);
    await waitForRequest('@defaultRequest');

    // The button is only enabled with a selection.
    await selectFirstThreeRows();
    await waitFor(() => expect(getDeleteMultipleButton()).toBeEnabled());

    await userEvent.click(getDeleteMultipleButton());
    await userEvent.click(await screen.findByText(labelDelete));

    const { request } = await waitForRequest('@deleteNotificationsRequest');

    expect(request.body).toEqual({ ids: [1, 2, 3] });
    expect(
      await screen.findByText(labelNotificationsSuccessfullyDeleted)
    ).toBeVisible();

    await waitForRequest('@defaultRequest');

    await expectNoRowSelected();
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
  });

  it('displays a warning message containing the names of the notifications that failed to be deleted if the deletion of some notifications fails', async () => {
    mockedBulkDelete(multipleNotificationsWarningResponse);
    await waitForRequest('@defaultRequest');

    const warningMessage = `${labelFailedToDeleteNotifications}: notification1, notification2`;

    await selectFirstThreeRows();

    await userEvent.click(getDeleteMultipleButton());
    await userEvent.click(await screen.findByText(labelDelete));

    await waitForRequest('@deleteNotificationsRequest');
    await waitForRequest('@defaultRequest');
    expect(await screen.findByText(warningMessage)).toBeVisible();

    await expectNoRowSelected();
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
  });

  it('displays an error message if the deletion of all notifications fails', async () => {
    mockedBulkDelete(multipleNotificationsfailedResponse);
    await waitForRequest('@defaultRequest');

    await selectFirstThreeRows();

    await userEvent.click(getDeleteMultipleButton());
    await userEvent.click(await screen.findByText(labelDelete));

    await waitForRequest('@deleteNotificationsRequest');
    expect(
      await screen.findByText(labelFailedToDeleteSelectedNotifications)
    ).toBeVisible();

    await expectNoRowSelected();
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
  });
});

describe('Listing row actions: Delete button', () => {
  beforeEach(() => {
    expect.hasAssertions();
    interceptDefaultListing();

    interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: {
        data: [
          {
            status: 204
          }
        ]
      }
    });

    renderListing();
  });

  it('displays a confirmation dialog containing the notification name upon clicking the Delete button', async () => {
    await waitForRequest('@defaultRequest');

    const message = `${labelDelete} « notification1 ».`;
    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteNotification))[0]
    );
    expect(await screen.findByText(message)).toBeVisible();
    expect(screen.getByText(labelDeleteNotification)).toBeVisible();
    expect(screen.getByText(labelDeleteNotificationWarning)).toBeVisible();
    await userEvent.click(screen.getByText(labelCancel));

    await waitFor(() =>
      expect(screen.queryByText(message)).not.toBeInTheDocument()
    );
    expect(getRequestCalls('@deleteNotificationtRequest')).toHaveLength(0);
  });

  it('displays a success message after successful deletion', async () => {
    await waitForRequest('@defaultRequest');

    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteNotification))[0]
    );
    await userEvent.click(await screen.findByLabelText(labelDelete));

    await waitForRequest('@deleteNotificationtRequest');
    await waitForRequest('@defaultRequest');

    expect(
      await screen.findByText(labelNotificationSuccessfullyDeleted)
    ).toBeVisible();
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
  });

  it('displays an error message upon failed deletion', async () => {
    interceptAPIRequest({
      alias: 'deleteNotificationtRequest',
      method: Method.DELETE,
      path: notificationEndpoint({ id: 1 }),
      response: {
        code: 'ok',
        message: 'internal server error'
      },
      statusCode: 500
    });

    await waitForRequest('@defaultRequest');

    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteNotification))[0]
    );
    await userEvent.click(await screen.findByLabelText(labelDelete));
    await waitForRequest('@deleteNotificationtRequest');

    expect(await screen.findByText('internal server error')).toBeVisible();
    await waitFor(() =>
      expect(
        screen.queryByText(labelDeleteNotification)
      ).not.toBeInTheDocument()
    );
  });
});

describe('Listing row actions: Duplicate button', () => {
  beforeEach(() => {
    expect.hasAssertions();
    interceptDefaultListing();

    interceptAPIRequest({
      alias: 'getNotificationRequest',
      method: Method.GET,
      path: notificationEndpoint({ id: 1 }),
      response: getNotificationResponse({})
    });

    interceptAPIRequest({
      alias: 'duplicateNotificationtRequest',
      method: Method.POST,
      path: notificationEndpoint({}),
      response: { status: 'ok' }
    });

    renderListing();
  });

  it('displays a confirmation dialog with a text field for the new notification name when the Duplicate button is clicked', async () => {
    await waitForRequest('@defaultRequest');

    const dialog = await openDuplicateDialog();

    expect(
      within(dialog).getByText(labelPleaseEnterNameForDuplicatedNotification)
    ).toBeVisible();
    expect(getNameField()).toBeVisible();
    expect(getNameField()).toHaveValue('');
    expect(
      within(dialog).getByRole('button', { name: labelDuplicate })
    ).toBeDisabled();

    await discardDuplication();
  });

  it('validates that the name field is not empty and the name does not already exist', async () => {
    await waitForRequest('@defaultRequest');

    await openDuplicateDialog();

    await waitForRequest('@getNotificationRequest');

    expect(getNameField()).toHaveAttribute('required');
    await userEvent.type(getNameField(), 'notification1');
    await userEvent.tab();
    expect(await screen.findByText(labelThisNameAlreadyExists)).toBeVisible();

    await userEvent.clear(getNameField());
    await userEvent.tab();
    expect(await screen.findByText(labelRequired)).toBeVisible();

    await discardDuplication();
  });

  it('disables the Confirm button if the name is empty or already exists', async () => {
    await waitForRequest('@defaultRequest');

    await openDuplicateDialog();
    await userEvent.type(getNameField(), 'notification1');
    await waitFor(() => expect(screen.getByTestId('Confirm')).toBeDisabled());

    // From a valid name, so that emptying it is what disables Confirm.
    await userEvent.clear(getNameField());
    await userEvent.type(getNameField(), 'New name');
    await waitFor(() => expect(screen.getByTestId('Confirm')).toBeEnabled());

    await userEvent.clear(getNameField());
    await waitFor(() => expect(screen.getByTestId('Confirm')).toBeDisabled());
    // An empty name is back to the initial value, which alone disables
    // Confirm: the required message shows the rule applies.
    await userEvent.tab();
    expect(await screen.findByText(labelRequired)).toBeVisible();

    await discardDuplication();
  });

  it('displays a success message after a successful duplication', async () => {
    await waitForRequest('@defaultRequest');

    await openDuplicateDialog();
    await waitForRequest('@getNotificationRequest');

    await userEvent.type(getNameField(), 'New name');
    await waitFor(() => expect(screen.getByTestId('Confirm')).toBeEnabled());
    await userEvent.click(screen.getByTestId('Confirm'));

    const { request } = await waitForRequest('@duplicateNotificationtRequest');

    expect(request.body).toEqual(expect.objectContaining({ name: 'New name' }));
    await waitForRequest('@defaultRequest');

    expect(await screen.findByText(labelNotificationDuplicated)).toBeVisible();
    await waitFor(() =>
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    );
  });

  it('displays an error message upon failed duplication request', async () => {
    await waitForRequest('@defaultRequest');

    const errorMessage = 'internal server error';

    interceptAPIRequest({
      alias: 'duplicateNotificationtRequest',
      method: Method.POST,
      path: notificationEndpoint({}),
      response: {
        code: '500',
        message: errorMessage
      },
      statusCode: 500
    });

    await openDuplicateDialog();
    await waitForRequest('@getNotificationRequest');

    await userEvent.type(getNameField(), 'New name');
    await waitFor(() => expect(screen.getByTestId('Confirm')).toBeEnabled());
    await userEvent.click(screen.getByTestId('Confirm'));

    await waitForRequest('@duplicateNotificationtRequest');

    expect(await screen.findByText(errorMessage)).toBeVisible();
    expect(
      screen.queryByText(labelNotificationDuplicated)
    ).not.toBeInTheDocument();
  });
});

describe('column sorting', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initializeSorting();
  });

  it('executes a listing request when a sortable column is clicked', async () => {
    await waitForRequest('@defaultRequest');

    expect(columnToSort.length).toBeGreaterThan(0);

    for (const { label, id, sortField } of columnToSort) {
      const sortBy = getSortBy({ id, sortField });

      await userEvent.click(screen.getByLabelText(`Column ${label}`));

      await waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `dataToListingTableDesc${label}`
      });

      await userEvent.click(screen.getByLabelText(`Column ${label}`));

      await waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `dataToListingTableAsc${label}`
      });

      expect(await screen.findByText('notification1')).toBeInTheDocument();
    }
  });
});
