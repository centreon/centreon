import { type ListingParameters, SnackbarProvider } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  type RecordedRequest,
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
import { resourceAccessRuleEndpoint } from '../../AddEditResourceAccessRule/api/endpoints';
import { findResourceAccessRuleResponse } from '../../AddEditResourceAccessRule/specs/testUtils';
import {
  labelActions,
  labelActiveOrInactive,
  labelCancel,
  labelDelete,
  labelDeleteMultipleResourceAccessRules,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRules,
  labelDeleteResourceAccessRulesDialogMessage,
  labelDeleteResourceAccessRulesWarning,
  labelDeleteResourceAccessRuleWarning,
  labelDescription,
  labelDuplicate,
  labelEnterNameForDuplicatedRule,
  labelFailedToDeleteSelectedRules,
  labelName,
  labelNameAlreadyExists,
  labelRequired,
  labelResourceAccessRuleDeletedSuccess,
  labelResourceAccessRuleName,
  labelResourceAccessRulesDeletedSuccess,
  labelRuleDuplicatedSuccess,
  labelStatus
} from '../../translatedLabels';
import { buildResourceAccessRulesEndpoint } from '../api/endpoints';
import ResourceAccessRulesListing from '../Listing';
import {
  defaultQueryParams,
  deleteMultipleRulesFailedResponse,
  deleteMultipleRulesSuccessResponse,
  deleteMultipleRulesWarningResponse,
  getListingColumns,
  getListingResponse
} from './testUtils';

// The fixture parameters, as the listing builds them.
const listingEndpoint = (parameters: object): string =>
  buildResourceAccessRulesEndpoint(parameters as ListingParameters);

interface ExpectedQuery {
  key: string;
  value: string | object;
}

// A string is compared as it is, an object as the JSON the listing sends.
const expectQueries = (
  request: RecordedRequest,
  queries: Array<ExpectedQuery>
): void => {
  queries.forEach(({ key, value }) => {
    expect(request.url.searchParams.get(key)).toBe(
      typeof value === 'string' ? value : JSON.stringify(value)
    );
  });
};

const waitForRequestAndVerifyQueries = async ({
  queries,
  requestAlias
}: {
  queries: Array<ExpectedQuery>;
  requestAlias: string;
}): Promise<void> => {
  const { request } = await waitForRequest(`@${requestAlias}`);

  expectQueries(request, queries);
};

const ListingWithDialogs = (): JSX.Element => (
  <div>
    <ResourceAccessRulesListing />
    <DeleteConfirmationDialog />
    <DuplicationForm />
  </div>
);

const renderListing = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>
          <SnackbarProvider>
            <ListingWithDialogs />
          </SnackbarProvider>
        </QueryClientProvider>
      </Provider>
    </div>
  );
};

const interceptDefaultListing = (): void => {
  interceptAPIRequest({
    alias: 'defaultRequest',
    method: Method.GET,
    path: listingEndpoint(defaultQueryParams),
    response: getListingResponse({})
  });
};

const initialize = (): void => {
  interceptDefaultListing();

  interceptAPIRequest({
    alias: 'secondPageRequest',
    method: Method.GET,
    path: listingEndpoint({
      ...defaultQueryParams,
      page: 2
    }),
    query: { name: 'page', value: '2' },
    response: getListingResponse({ page: 2 })
  });

  interceptAPIRequest({
    alias: 'lastPageRequest',
    method: Method.GET,
    path: listingEndpoint({
      ...defaultQueryParams,
      page: 7
    }),
    query: { name: 'page', value: '7' },
    response: getListingResponse({ page: 7 })
  });

  interceptAPIRequest({
    alias: 'listingWithLimit',
    method: Method.GET,
    path: listingEndpoint({
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

// Each sorted listing is answered only for its own `sort_by`, every other
// listing request by the default mock.
const initializeSorting = (): void => {
  interceptDefaultListing();

  columnToSort.forEach(({ id, label, sortField }) => {
    const sortBy = (sortField || id) as string;

    (['desc', 'asc'] as const).forEach((order) => {
      const sort = { [sortBy]: order };

      interceptAPIRequest({
        alias: `dataToListingTable${order === 'desc' ? 'Desc' : 'Asc'}${label}`,
        method: Method.GET,
        path: listingEndpoint({ ...defaultQueryParams, sort }),
        query: { name: 'sort_by', value: JSON.stringify(sort) },
        response: getListingResponse({})
      });
    });
  });

  renderListing();
};

// The sort label of a sortable column, which carries its sort state.
const getSortLabel = (label: string): HTMLElement =>
  screen.getByLabelText(`Column ${label}`);

const expectSortedAscendingBy = (label: string): void => {
  expect(getSortLabel(label)).toHaveClass(
    'Mui-active',
    'MuiTableSortLabel-directionAsc'
  );
};

// The checkbox inside a status switch, which holds its state and gets the
// click.
const getStatusSwitches = (): Array<HTMLInputElement> =>
  screen
    .getAllByLabelText(labelActiveOrInactive)
    .map((element) => element.querySelector('input') as HTMLInputElement);

const getMassDeleteButton = (): HTMLElement =>
  screen.getByTestId(labelDeleteMultipleResourceAccessRules);

// The listing's row click handler prevents the default action of the click,
// so the browser resets the native checkbox after React rendered it checked:
// the checkbox drawn on screen, which follows the selection, is what is read.
const getRowCheckbox = (row: number): HTMLElement =>
  screen
    .getByLabelText(`Select row ${row}`)
    .closest('.MuiCheckbox-root') as HTMLElement;

// Each selection is applied before the next click, which would otherwise
// start from the previous selection.
const selectFirstThreeRows = async (): Promise<void> => {
  for (const row of [1, 2, 3]) {
    await userEvent.click(await screen.findByLabelText(`Select row ${row}`));
    await waitFor(() => {
      expect(getRowCheckbox(row)).toHaveClass('Mui-checked');
    });
  }
};

// The pagination buttons are disabled while the page loads.
const clickWhenEnabled = async (label: string): Promise<void> => {
  await waitFor(() => {
    expect(screen.getByLabelText(label)).toBeEnabled();
  });
  await userEvent.click(screen.getByLabelText(label));
};

const getDialog = (): HTMLElement => screen.getByRole('dialog');

const getDuplicateNameInput = (): HTMLElement =>
  screen.getByLabelText(labelResourceAccessRuleName);

const openDuplicationForm = async (): Promise<void> => {
  await waitForRequest('@defaultRequest');

  await userEvent.click((await screen.findAllByTestId(labelDuplicate))[0]);
  await waitForRequest('@getRequest');
  await screen.findByText(labelEnterNameForDuplicatedRule);
};

// The listing request was sent again after a change, as the deletion
// invalidates the listing.
const waitForListingRefetch = async (sentBefore: number): Promise<void> => {
  await waitFor(() => {
    expect(
      getRequestCalls('@defaultRequest').filter(({ answered }) => answered)
        .length
    ).toBeGreaterThan(sentBefore);
  });
};

beforeEach(() => {
  expect.hasAssertions();
});

// The select shows the limit once the listing applied it: choosing a value
// while it still shows that value would change nothing.
const selectRowsPerPage = async (value: string): Promise<void> => {
  await waitFor(() => {
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });
  await userEvent.click(
    document.querySelector('#Rows\\ per\\ page') as HTMLElement
  );
  await userEvent.click(
    within(await screen.findByRole('listbox')).getByRole('option', {
      name: value
    })
  );
  await waitFor(() => {
    expect(
      document.querySelector('#Rows\\ per\\ page') as HTMLElement
    ).toHaveTextContent(value);
  });
};

describe('Resource Access Rules Listing', () => {
  beforeEach(initialize);

  it('displays the first page of the resource access rules listing', async () => {
    await waitForRequest('@defaultRequest');

    expect(await screen.findByText('rule1')).toBeVisible();

    expect(getSortLabel(labelName)).toBeVisible();
    expect(getSortLabel(labelDescription)).toBeVisible();
    expect(screen.getByText(labelActions)).toBeVisible();
    expect(screen.getByText(labelStatus)).toBeVisible();
    expectSortedAscendingBy(labelName);
    expect(getSortLabel(labelDescription)).not.toHaveClass('Mui-active');
    expect(screen.getByText('1-10 of 64')).toBeVisible();

    const rows = screen.getAllByRole('row').slice(1);

    expect(rows).toHaveLength(64);
    expect(within(rows[0]).getByText('rule0')).toBeVisible();
    expect(within(rows[0]).getByText('resourceAccessRule1')).toBeVisible();
    expect(within(rows[1]).getByText('rule1')).toBeVisible();

    // Rules alternate between inactive (even ones) and active.
    const switches = getStatusSwitches();

    expect(switches[0]).not.toBeChecked();
    expect(switches[1]).toBeChecked();

    expect(
      within(rows[0]).getByTestId(labelDeleteResourceAccessRule)
    ).toBeEnabled();
    expect(within(rows[0]).getByTestId(labelDuplicate)).toBeEnabled();
    expect(getMassDeleteButton()).toBeDisabled();
  });

  it('executes a get resource access rules request after updating limit param', async () => {
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

    expect(await screen.findByText('rule1')).toBeVisible();
    await waitFor(() => {
      expect(screen.getByText('1-10 of 64')).toBeVisible();
    });
  });

  it('executes a listing request with an updated page parameter when change page action is clicked', async () => {
    await waitForRequest('@defaultRequest');

    await clickWhenEnabled('Next page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '2' }],
      requestAlias: 'secondPageRequest'
    });

    await clickWhenEnabled('Previous page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    await clickWhenEnabled('Last page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '7' }],
      requestAlias: 'lastPageRequest'
    });

    await clickWhenEnabled('First page');
    await waitForRequestAndVerifyQueries({
      queries: [{ key: 'page', value: '1' }],
      requestAlias: 'defaultRequest'
    });

    expect(await screen.findByText('rule1')).toBeVisible();
    await waitFor(() => {
      expect(screen.getByText('1-10 of 64')).toBeVisible();
    });
  });
});

describe('column sorting', () => {
  beforeEach(initializeSorting);

  it('executes a listing request when a sortable column is clicked', async () => {
    await waitForRequest('@defaultRequest');

    expect(columnToSort.length).toBeGreaterThan(0);

    for (const { label, id, sortField } of columnToSort) {
      const sortBy = (sortField || id) as string;

      await userEvent.click(await screen.findByLabelText(`Column ${label}`));

      await waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'desc' } }],
        requestAlias: `dataToListingTableDesc${label}`
      });

      await userEvent.click(screen.getByLabelText(`Column ${label}`));

      await waitForRequestAndVerifyQueries({
        queries: [{ key: 'sort_by', value: { [sortBy]: 'asc' } }],
        requestAlias: `dataToListingTableAsc${label}`
      });

      expect(await screen.findByText('rule1')).toBeVisible();
      await waitFor(() => {
        expectSortedAscendingBy(label);
      });
      expect(getSortLabel(labelName)).not.toHaveClass('Mui-active');
    }
  });
});

describe('Listing row actions: Delete button', () => {
  beforeEach(() => {
    interceptDefaultListing();

    interceptAPIRequest({
      alias: 'deleteResourceAccessRuleRequest',
      method: Method.DELETE,
      path: resourceAccessRuleEndpoint({ id: 1 }),
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

  it("displays a confirmation dialog containing the resource access rule's name upon clicking on Delete button in rule listing", async () => {
    await waitForRequest('@defaultRequest');

    const message = `rule0 ${labelDeleteResourceAccessRuleDialogMessage}`;
    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteResourceAccessRule))[0]
    );
    expect(
      await screen.findByText(labelDeleteResourceAccessRule)
    ).toBeVisible();
    expect(screen.getByText(message)).toBeVisible();
    expect(
      screen.getByText(labelDeleteResourceAccessRuleWarning)
    ).toBeVisible();
    expect(screen.getByTestId(labelCancel)).toBeVisible();
    expect(screen.getByLabelText(labelDelete)).toBeVisible();

    expect(screen.getByLabelText(labelDelete)).toBeEnabled();
    expect(screen.getByTestId(labelCancel)).toBeEnabled();
    await userEvent.click(screen.getByTestId(labelCancel));
  });

  it('closes a delete confirmation dialog when Cancel button is clicked', async () => {
    await waitForRequest('@defaultRequest');

    const message = `rule0 ${labelDeleteResourceAccessRuleDialogMessage}`;
    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteResourceAccessRule))[0]
    );
    expect(
      await screen.findByText(labelDeleteResourceAccessRule)
    ).toBeVisible();
    expect(screen.getByText(message)).toBeVisible();
    expect(
      screen.getByText(labelDeleteResourceAccessRuleWarning)
    ).toBeVisible();
    expect(screen.getByTestId(labelCancel)).toBeVisible();
    expect(screen.getByLabelText(labelDelete)).toBeVisible();

    await userEvent.click(screen.getByTestId(labelCancel));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(
      screen.queryByText(labelDeleteResourceAccessRule)
    ).not.toBeInTheDocument();
    expect(screen.queryByText(message)).not.toBeInTheDocument();
    expect(
      screen.queryByText(labelDeleteResourceAccessRuleWarning)
    ).not.toBeInTheDocument();
    expect(screen.queryByTestId(labelCancel)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(labelDelete)).not.toBeInTheDocument();

    expect(getRequestCalls('@deleteResourceAccessRuleRequest')).toHaveLength(0);
    expect(screen.getByText('rule0')).toBeVisible();
  });

  it('displays a success message after successful deletion', async () => {
    await waitForRequest('@defaultRequest');
    const listingRequestsBefore = getRequestCalls('@defaultRequest').length;

    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteResourceAccessRule))[0]
    );
    await userEvent.click(await screen.findByLabelText(labelDelete));

    const { request } = await waitForRequest(
      '@deleteResourceAccessRuleRequest'
    );
    expect(request.url.pathname).toMatch(
      /\/administration\/resource-access\/rules\/1$/
    );
    await waitForListingRefetch(listingRequestsBefore);

    expect(
      await screen.findByText(labelResourceAccessRuleDeletedSuccess)
    ).toBeVisible();
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('displays an error message upon failed deletion', async () => {
    interceptAPIRequest({
      alias: 'deleteResourceAccessRuleFailedRequest',
      method: Method.DELETE,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    await waitForRequest('@defaultRequest');

    await userEvent.click(
      (await screen.findAllByTestId(labelDeleteResourceAccessRule))[0]
    );
    await userEvent.click(await screen.findByLabelText(labelDelete));
    await waitForRequest('@deleteResourceAccessRuleFailedRequest');

    expect(await screen.findByText('internal server error')).toBeVisible();
    // The dialog closes once the request settled, even on failure.
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(
      screen.queryByText(labelResourceAccessRuleDeletedSuccess)
    ).not.toBeInTheDocument();
  });
});

describe('Listing row actions enable-disable action', () => {
  it('displays an error message upon failed disabling', async () => {
    interceptDefaultListing();

    interceptAPIRequest({
      alias: 'activateRuleRequest',
      method: Method.PATCH,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    renderListing();

    await waitForRequest('@defaultRequest');

    await screen.findAllByLabelText(labelActiveOrInactive);
    await userEvent.click(getStatusSwitches()[0]);
    const { request } = await waitForRequest('@activateRuleRequest');

    // rule0 is inactive: the switch asks to enable it.
    expect(request.body).toEqual({ is_enabled: true });
    expect(await screen.findByText('internal server error')).toBeVisible();
  });
});

describe('Listing header actions: mass delete', () => {
  beforeEach(() => {
    interceptDefaultListing();

    renderListing();
  });

  it('confirms that multiple delete button is disabled when no resource access rule is selected and it becomes active once at least one row is selected', async () => {
    await waitForRequest('@defaultRequest');

    expect(
      await screen.findByTestId(labelDeleteMultipleResourceAccessRules)
    ).toBeDisabled();
    await selectFirstThreeRows();
    await waitFor(() => {
      expect(getMassDeleteButton()).toBeEnabled();
    });

    expect(getRowCheckbox(1)).toHaveClass('Mui-checked');
    expect(getRowCheckbox(2)).toHaveClass('Mui-checked');
    expect(getRowCheckbox(3)).toHaveClass('Mui-checked');
    expect(getRowCheckbox(4)).not.toHaveClass('Mui-checked');
  });

  // The button is enabled only once a row is selected, so the tests below
  // select the first three rules first.
  it('displays a confirmation dialog when multiple delete button is clicked', async () => {
    await waitForRequest('@defaultRequest');
    await selectFirstThreeRows();

    await userEvent.click(getMassDeleteButton());

    expect(
      await screen.findByText(labelDeleteResourceAccessRules)
    ).toBeVisible();
    expect(
      screen.getByText(labelDeleteResourceAccessRulesDialogMessage)
    ).toBeVisible();
    expect(
      screen.getByText(labelDeleteResourceAccessRulesWarning)
    ).toBeVisible();
    expect(within(getDialog()).getByText(labelDelete)).toBeVisible();
    expect(within(getDialog()).getByText(labelCancel)).toBeVisible();

    // The multiple deletion names no rule.
    expect(within(getDialog()).queryByText(/rule0/)).not.toBeInTheDocument();

    await userEvent.click(screen.getByText(labelCancel));
  });

  it('displays a success message when all selected rules are deleted', async () => {
    interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesSuccessResponse,
      statusCode: 207
    });

    await waitForRequest('@defaultRequest');
    await selectFirstThreeRows();
    await userEvent.click(getMassDeleteButton());
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByText(labelDelete)
    );

    const { request } = await waitForRequest(
      '@deleteResourceAccessRulesRequest'
    );
    expect(request.body).toEqual({ ids: [1, 2, 3] });
    expect(
      await screen.findByText(labelResourceAccessRulesDeletedSuccess)
    ).toBeVisible();

    // The selection is cleared once the rules are deleted.
    await waitFor(() => {
      expect(getMassDeleteButton()).toBeDisabled();
    });
    expect(getRowCheckbox(1)).not.toHaveClass('Mui-checked');
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('displays a warning message containing the list of names that failed to delete', async () => {
    interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesWarningResponse,
      statusCode: 200
    });
    const warningMessage = `${labelFailedToDeleteSelectedRules}: rule0, rule1`;

    await waitForRequest('@defaultRequest');
    const listingRequestsBefore = getRequestCalls('@defaultRequest').length;
    await selectFirstThreeRows();
    await userEvent.click(getMassDeleteButton());
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByText(labelDelete)
    );

    await waitForRequest('@deleteResourceAccessRulesRequest');
    await waitForListingRefetch(listingRequestsBefore);
    expect(await screen.findByText(warningMessage)).toBeVisible();
  });

  it('displays an error message when the deletion of all rules fails', async () => {
    interceptAPIRequest({
      alias: 'deleteResourceAccessRulesRequest',
      method: Method.POST,
      path: `${resourceAccessRuleEndpoint({})}/_delete`,
      response: deleteMultipleRulesFailedResponse,
      statusCode: 200
    });

    await waitForRequest('@defaultRequest');
    await selectFirstThreeRows();
    await userEvent.click(getMassDeleteButton());
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByText(labelDelete)
    );

    await waitForRequest('@deleteResourceAccessRulesRequest');
    expect(
      await screen.findByText(labelFailedToDeleteSelectedRules)
    ).toBeVisible();
  });
});

describe('Listing action: duplicate a rule', () => {
  beforeEach(() => {
    interceptDefaultListing();

    interceptAPIRequest({
      alias: 'getRequest',
      method: Method.GET,
      path: resourceAccessRuleEndpoint({ id: 1 }),
      response: findResourceAccessRuleResponse()
    });

    interceptAPIRequest({
      alias: 'postRequest',
      method: Method.POST,
      path: resourceAccessRuleEndpoint({}),
      response: { status: 'ok' }
    });

    renderListing();
  });

  it('opens a duplication form when a duplicate button is clicked', async () => {
    await openDuplicationForm();

    expect(screen.getByText(labelEnterNameForDuplicatedRule)).toBeVisible();
    expect(getDuplicateNameInput()).toBeVisible();
    expect(screen.getByText(labelCancel)).toBeVisible();
    expect(within(getDialog()).getByText(labelDuplicate)).toBeVisible();

    // The name is prefilled from the duplicated rule.
    expect(getDuplicateNameInput()).toHaveValue('rule0_1');
    expect(
      within(getDialog()).getByRole('button', { name: labelDuplicate })
    ).toBeEnabled();

    await userEvent.click(screen.getByText(labelCancel));
  });

  it('displays required error message when the name remains empty', async () => {
    await openDuplicationForm();

    await userEvent.clear(getDuplicateNameInput());
    await userEvent.click(screen.getByText(labelEnterNameForDuplicatedRule));

    expect(await screen.findByText(labelRequired)).toBeVisible();
    expect(
      within(getDialog()).getByRole('button', { name: labelDuplicate })
    ).toBeDisabled();

    await userEvent.click(screen.getByText(labelCancel));
  });

  it('displays an error message when an already existing rule name is entered', async () => {
    await openDuplicationForm();

    await userEvent.clear(getDuplicateNameInput());
    await userEvent.type(getDuplicateNameInput(), 'rule0');

    await userEvent.click(screen.getByText(labelEnterNameForDuplicatedRule));
    expect(await screen.findByText(labelNameAlreadyExists)).toBeVisible();
    expect(getDuplicateNameInput()).toHaveValue('rule0');
    await waitFor(() => {
      expect(
        within(getDialog()).getByRole('button', { name: labelDuplicate })
      ).toBeDisabled();
    });

    await userEvent.click(screen.getByText(labelCancel));
  });

  it('creates a duplicated rule', async () => {
    await openDuplicationForm();

    await userEvent.clear(getDuplicateNameInput());
    await userEvent.type(getDuplicateNameInput(), 'my_rule');

    const duplicateButton = within(getDialog()).getByRole('button', {
      name: labelDuplicate
    });
    await waitFor(() => {
      expect(duplicateButton).toBeEnabled();
    });
    await userEvent.click(within(getDialog()).getByText(labelDuplicate));

    const { request } = await waitForRequest('@postRequest');

    expect(request.body).toEqual(expect.objectContaining({ name: 'my_rule' }));
    expect(request.body).not.toHaveProperty('id');

    expect(await screen.findByText(labelRuleDuplicatedSuccess)).toBeVisible();
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('displays an error message when duplication of a rule fails', async () => {
    interceptAPIRequest({
      alias: 'postErrorRequest',
      method: Method.POST,
      path: resourceAccessRuleEndpoint({}),
      response: {
        message: 'internal server error'
      },
      statusCode: 500
    });

    await openDuplicationForm();
    await userEvent.clear(getDuplicateNameInput());
    await userEvent.type(getDuplicateNameInput(), 'my_rule');

    await waitFor(() => {
      expect(
        within(getDialog()).getByRole('button', { name: labelDuplicate })
      ).toBeEnabled();
    });
    await userEvent.click(within(getDialog()).getByText(labelDuplicate));
    await waitForRequest('@postErrorRequest');

    expect(await screen.findByText('internal server error')).toBeVisible();
    expect(
      screen.queryByText(labelRuleDuplicatedSuccess)
    ).not.toBeInTheDocument();
  });
});
