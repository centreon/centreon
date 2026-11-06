import { rs } from '@rstest/core';
import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { getTokenEndpoint } from '../api/endpoints';
import { Column } from '../Listing/Columns/models';
import {
  labelAdd,
  labelCancel,
  labelCreateAuthenticationToken,
  labelCreator,
  labelDeleteToken,
  labelDisabled,
  labelDisableToken,
  labelDone,
  labelDuration,
  labelEnableDisable,
  labelEnableToken,
  labelFilters,
  labelGenerateToken,
  labelName,
  labelNeverExpire,
  labelRefresh,
  labelSearch,
  labelSecurityToken,
  labelTokenCopiedToTheClipboard,
  labelTokenDeleted,
  labelTokenDisabled,
  labelTokenEnabled,
  labelType,
  labelUser
} from '../translatedLabels';
import { initialize } from './initialize';
import {
  listTokensWithDeletedCreatorResponse,
  tokenDetailsResponse
} from './utils';

const tokenNames = [
  'a-token',
  'b-token',
  'c-token',
  'd-token',
  'e-token',
  'f-token',
  'g-token',
  'h-token',
  'i-token'
];

// CMA and poller tokens can be copied again, API tokens cannot.
const copyableTokens = ['b-token', 'd-token', 'e-token', 'g-token'];
const revokedTokens = ['b-token', 'e-token', 'g-token'];

// The listing replaces the loading skeleton once the tokens are loaded.
const waitForListing = async (): Promise<void> => {
  await waitForRequest('@listToken');

  expect(await screen.findByText('a-token')).toBeVisible();
};

const getRow = (name: string): HTMLElement => {
  const row = screen.getByText(name).closest('[role="row"]');

  if (!row) {
    throw new Error(`No row for ${name}`);
  }

  return row as HTMLElement;
};

const getStatusSwitch = (name: string): HTMLInputElement =>
  within(screen.getByTestId(`${labelEnableDisable}_${name}_23`)).getByRole(
    'checkbox'
  ) as HTMLInputElement;

const getSearchParam = (): unknown => {
  const search = getRequestCalls('@listToken')
    .at(-1)
    ?.request.url.searchParams.get('search');

  return search ? JSON.parse(search) : null;
};

const getFiltersBadge = (): HTMLElement | null =>
  screen
    .getByTestId(labelFilters)
    .closest('.MuiBadge-root')
    ?.querySelector('.MuiBadge-badge') ?? null;

const getSubmitButton = (): HTMLElement => screen.getByTestId('submit');

// The name field puts its test id on the wrapper and on the input.
const getNameInput = async (): Promise<HTMLElement> =>
  (await screen.findAllByTestId(labelName))[1];

const openCreationModal = async (): Promise<HTMLElement> => {
  await userEvent.click(screen.getByTestId(labelAdd));

  expect(await screen.findByText(labelCreateAuthenticationToken)).toBeVisible();

  return screen.getByRole('dialog');
};

// The type, duration and user fields put their test id on the input.
const selectType = async (type: string): Promise<void> => {
  await userEvent.click(screen.getByTestId(labelType));
  await userEvent.click(await screen.findByRole('option', { name: type }));
};

// What the creation form shows once the token is generated.
const expectGeneratedToken = async (dialog: HTMLElement): Promise<void> => {
  const tokenInput = await screen.findByTestId('tokenInput');

  expect(tokenInput).toBeVisible();
  expect(tokenInput).toHaveValue(tokenDetailsResponse.token);
  expect(tokenInput).toHaveAttribute('type', 'password');

  expect((await screen.findAllByTestId(labelName))[1]).toBeDisabled();
  expect(screen.getByTestId(labelType)).toBeDisabled();

  expect(getSubmitButton()).toHaveTextContent(new RegExp(`^${labelDone}$`));
  expect(getSubmitButton()).toBeEnabled();
  expect(
    within(dialog).queryByRole('button', { name: labelCancel })
  ).not.toBeInTheDocument();
};

describe('Authentication tokens', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize();
  });

  afterEach(() => {
    rs.restoreAllMocks();
  });

  it('renders the authentication token page and checks for the presence of all elements', async () => {
    await waitForRequest('@listToken');

    expect(await screen.findByText('a-token')).toBeVisible();

    expect(screen.getAllByText('API')[0]).toBeVisible();

    // Every column, in this order from left to right.
    const headerPositions = [
      Column.Name,
      Column.Type,
      Column.User,
      Column.Creator,
      Column.CreationDate,
      Column.ExpirationDate,
      Column.Actions,
      Column.Activate
    ].map((label) => {
      const header = screen.getByText(label);

      expect(header).toBeVisible();

      return header.getBoundingClientRect().left;
    });

    expect(headerPositions).toEqual(
      [...headerPositions].sort((left, right) => left - right)
    );

    const rows = screen
      .getAllByRole('row')
      .filter((row) =>
        tokenNames.some((name) => within(row).queryByText(name))
      );

    expect(rows.map((row) => row.textContent?.slice(0, 7))).toEqual(tokenNames);

    const bToken = getRow('b-token');

    expect(within(bToken).getByText('CMA')).toBeVisible();
    // The September 31st the API sent is read as October 1st.
    expect(within(bToken).getByText('10/01/2023')).toBeVisible();
    // Midnight UTC, in the user's time zone.
    expect(within(bToken).getByText('09/15/2028 2:00 AM')).toBeVisible();

    expect(within(getRow('a-token')).getByText(labelNeverExpire)).toBeVisible();
    expect(within(getRow('c-token')).getByText('Kevin B')).toBeVisible();
    expect(within(getRow('d-token')).getByText('Poller')).toBeVisible();

    tokenNames.forEach((name) => {
      expect(screen.getByTestId(`Delete_${name}_23`)).toBeVisible();

      if (copyableTokens.includes(name)) {
        expect(screen.getByTestId(`Copy_${name}_23`)).toBeVisible();
      } else {
        expect(screen.queryByTestId(`Copy_${name}_23`)).not.toBeInTheDocument();
      }

      if (revokedTokens.includes(name)) {
        expect(getStatusSwitch(name)).not.toBeChecked();
      } else {
        expect(getStatusSwitch(name)).toBeChecked();
      }
    });

    expect(screen.getByText('1-9 of 9')).toBeVisible();
  });

  it('refreshes the page when the refresh button is clicked', async () => {
    await waitForRequest('@listToken');

    expect(await screen.findByText('a-token')).toBeVisible();

    await userEvent.click(screen.getByTestId(labelRefresh));
    await waitForRequest('@listToken');

    expect(getRequestCalls('@listToken')).toHaveLength(2);
    expect(await screen.findByText('i-token')).toBeVisible();
    expect(screen.getByText('1-9 of 9')).toBeVisible();
  });

  it('searches for tokens using the search bar and verifies the API request', async () => {
    await waitForListing();

    const search = screen.getAllByPlaceholderText(labelSearch)[0];

    await userEvent.clear(search);
    await userEvent.type(search, 'token');

    // The search is debounced: wait for the request carrying the whole text.
    await waitFor(
      () =>
        expect(getSearchParam()).toEqual({
          $and: [{ $or: [{ token_name: { $rg: 'token' } }] }, { $or: [] }]
        }),
      { timeout: 3000 }
    );

    expect(search).toHaveValue('token');
    // One filter differs from the default ones: the name.
    await waitFor(() => expect(getFiltersBadge()).toHaveTextContent(/^1$/));
  });

  it('applies filters and verifies the API request', async () => {
    await waitForListing();

    await userEvent.click(screen.getByTestId(labelFilters));

    const name = await getNameInput();

    await userEvent.clear(name);
    await userEvent.type(name, 'token 1');

    await userEvent.click(screen.getByTestId(labelDisabled));

    await userEvent.click(screen.getByTestId(labelSearch));

    await userEvent.click(screen.getByTestId(labelFilters));

    await waitFor(() =>
      expect(screen.queryByTestId(labelSearch)).not.toBeInTheDocument()
    );

    await waitFor(() =>
      expect(getSearchParam()).toEqual({
        $and: [
          { $or: [{ token_name: { $rg: 'token 1' } }] },
          { $or: [{ is_revoked: { $eq: true } }] }
        ]
      })
    );

    // The name filter shows in the search bar; the name and the status differ
    // from the default filters.
    expect(screen.getAllByPlaceholderText(labelSearch)[0]).toHaveValue(
      'token 1'
    );
    await waitFor(() => expect(getFiltersBadge()).toHaveTextContent(/^2$/));
  });

  it('deletes a token and verifies the deletion request', async () => {
    await waitForListing();

    await userEvent.click(await screen.findByTestId('Delete_d-token_23'));

    expect(await screen.findByText(labelDeleteToken)).toBeVisible();

    await userEvent.click(screen.getByTestId('confirm'));

    await waitForRequest('@deleteToken');
    await waitForRequest('@listToken');

    expect(await screen.findByText(labelTokenDeleted)).toBeVisible();
    // The confirmation dialog closes.
    await waitFor(() =>
      expect(screen.queryByText(labelDeleteToken)).not.toBeInTheDocument()
    );
  });

  it('disables a token and verifies the action', async () => {
    await waitForListing();

    await userEvent.click(
      within(await screen.findByTestId('Enable/Disable_d-token_23')).getByRole(
        'checkbox'
      )
    );

    expect(await screen.findByText(labelDisableToken)).toBeVisible();

    await userEvent.click(screen.getByTestId('confirm'));

    const { request } = await waitForRequest('@enableDisableToken');

    expect(request.url.pathname).toMatch(
      new RegExp(`${getTokenEndpoint({ tokenName: 'd-token', userId: 23 })}$`)
    );
    expect(request.body).toEqual({
      is_revoked: true
    });

    await waitForRequest('@listToken');

    expect(await screen.findByText(labelTokenDisabled)).toBeVisible();
    // The confirmation dialog closes.
    await waitFor(() =>
      expect(screen.queryByText(labelDisableToken)).not.toBeInTheDocument()
    );
    expect(getStatusSwitch('d-token')).not.toBeChecked();
  });

  it('enables a token and verifies the action', async () => {
    await waitForListing();

    await userEvent.click(
      within(await screen.findByTestId('Enable/Disable_e-token_23')).getByRole(
        'checkbox'
      )
    );

    expect(await screen.findByText(labelEnableToken)).toBeVisible();

    await userEvent.click(screen.getByTestId('confirm'));

    const { request } = await waitForRequest('@enableDisableToken');

    expect(request.url.pathname).toMatch(
      new RegExp(`${getTokenEndpoint({ tokenName: 'e-token', userId: 23 })}$`)
    );
    expect(request.body).toEqual({
      is_revoked: false
    });

    await waitForRequest('@listToken');

    expect(await screen.findByText(labelTokenEnabled)).toBeVisible();
    // The confirmation dialog closes.
    await waitFor(() =>
      expect(screen.queryByText(labelEnableToken)).not.toBeInTheDocument()
    );
    expect(getStatusSwitch('e-token')).toBeChecked();
  });

  it('copies a CMA token to the clipboard and verifies the action', async () => {
    await waitForListing();

    await userEvent.click(await screen.findByTestId('Copy_e-token_23'));

    await waitForRequest('@tokenDetails');

    expect(
      await screen.findByText(labelTokenCopiedToTheClipboard)
    ).toBeVisible();
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      tokenDetailsResponse.token
    );
  });

  it('adds a new API token and verifies the form submission', async () => {
    await waitForListing();

    const dialog = await openCreationModal();

    expect(getSubmitButton()).toBeDisabled();

    await userEvent.type(await getNameInput(), 'token 1');
    await userEvent.click(screen.getByTestId(labelUser));

    await userEvent.click(await screen.findByRole('option', { name: 'admin' }));

    // An API token is created for a user, by default for ever.
    expect(screen.getByTestId(labelType)).toHaveValue('API');
    expect(screen.getByTestId(labelDuration)).toHaveValue(labelNeverExpire);
    await waitFor(() =>
      expect(screen.getByTestId(labelUser)).toHaveValue('admin')
    );
    await waitFor(() => expect(getSubmitButton()).toBeEnabled());
    expect(getSubmitButton()).toHaveTextContent(
      new RegExp(`^${labelGenerateToken}$`)
    );

    await userEvent.click(getSubmitButton());

    const { request } = await waitForRequest('@addToken');

    expect(request.body).toEqual({
      expiration_date: null,
      name: 'token 1',
      type: 'api',
      user_id: 1
    });

    await expectGeneratedToken(dialog);
    expect(screen.getByTestId(labelUser)).toBeDisabled();

    expect(screen.getByText(labelSecurityToken)).toBeVisible();

    await userEvent.click(screen.getByText(labelDone));

    await waitFor(() =>
      expect(
        screen.queryByText(labelCreateAuthenticationToken)
      ).not.toBeInTheDocument()
    );
  });

  it('adds a new CMA token and verifies the form submission.', async () => {
    await waitForListing();

    const dialog = await openCreationModal();

    expect(getSubmitButton()).toBeDisabled();

    await userEvent.type(await getNameInput(), 'token 1');

    await selectType('Centreon monitoring agent');

    await waitFor(() =>
      expect(screen.queryByTestId(labelUser)).not.toBeInTheDocument()
    );

    expect(screen.getByTestId(labelType)).toHaveValue(
      'Centreon monitoring agent'
    );
    expect(screen.getByTestId(labelDuration)).toHaveValue(labelNeverExpire);
    await waitFor(() => expect(getSubmitButton()).toBeEnabled());

    await userEvent.click(getSubmitButton());

    const { request } = await waitForRequest('@addToken');

    // Not an API token: it belongs to the current user, who has no ID here.
    expect(request.body).toEqual({
      expiration_date: null,
      name: 'token 1',
      type: 'cma'
    });

    await expectGeneratedToken(dialog);

    expect(screen.queryByText(labelSecurityToken)).not.toBeInTheDocument();

    await userEvent.click(screen.getByText(labelDone));

    await waitFor(() =>
      expect(
        screen.queryByText(labelCreateAuthenticationToken)
      ).not.toBeInTheDocument()
    );
  });

  it('adds a new Poller token and verifies the form submission', async () => {
    await waitForListing();

    const dialog = await openCreationModal();

    expect(getSubmitButton()).toBeDisabled();

    await userEvent.type(await getNameInput(), 'token 1');

    await selectType('Poller');

    await waitFor(() =>
      expect(screen.queryByTestId(labelUser)).not.toBeInTheDocument()
    );

    expect(screen.getByTestId(labelType)).toHaveValue('Poller');
    expect(screen.getByTestId(labelDuration)).toHaveValue(labelNeverExpire);
    await waitFor(() => expect(getSubmitButton()).toBeEnabled());

    await userEvent.click(getSubmitButton());

    const { request } = await waitForRequest('@addToken');

    // Not an API token: it belongs to the current user, who has no ID here.
    expect(request.body).toEqual({
      expiration_date: null,
      name: 'token 1',
      type: 'poller'
    });

    await expectGeneratedToken(dialog);

    expect(screen.queryByText(labelSecurityToken)).not.toBeInTheDocument();

    await userEvent.click(screen.getByText(labelDone));

    await waitFor(() =>
      expect(
        screen.queryByText(labelCreateAuthenticationToken)
      ).not.toBeInTheDocument()
    );
  });
});

describe('Authentication tokens with a deleted creator', () => {
  beforeEach(() => {
    expect.hasAssertions();
    initialize(listTokensWithDeletedCreatorResponse);
  });

  afterEach(() => {
    rs.restoreAllMocks();
  });

  it('lists every token when the creator of one of them has been deleted', async () => {
    await waitForRequest('@listToken');

    expect(await screen.findByText('a-token')).toBeVisible();
    expect(screen.getByText('b-token')).toBeVisible();
    expect(screen.getByText('c-token')).toBeVisible();

    expect(screen.getByText('Deleted Creator')).toBeVisible();
  });

  it('does not offer a deleted creator in the creator filter', async () => {
    await waitForListing();

    await userEvent.click(screen.getByTestId(labelFilters));

    await userEvent.click(await screen.findByTestId(labelCreator));

    expect(
      await screen.findByRole('option', { name: 'Jane Doe' })
    ).toBeInTheDocument();
    expect(
      screen.queryByRole('option', { name: 'Deleted Creator' })
    ).not.toBeInTheDocument();
  });
});
