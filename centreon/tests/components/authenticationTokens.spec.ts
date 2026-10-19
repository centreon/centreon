import listTokens from '../../cypress/fixtures/authenticationTokens/listTokens.json';
import listTokensWithDeletedCreator from '../../cypress/fixtures/authenticationTokens/listTokensWithDeletedCreator.json';
import listUsers from '../../cypress/fixtures/authenticationTokens/listUsers.json';
import tokenDetails from '../../cypress/fixtures/authenticationTokens/tokenDetails.json';
import {
  labelAdd,
  labelCreateAuthenticationToken,
  labelCreator,
  labelDeleteToken,
  labelDisabled,
  labelDisableToken,
  labelDone,
  labelEnableToken,
  labelFilters,
  labelName,
  labelRefresh,
  labelSearch,
  labelSecurityToken,
  labelTokenDeleted,
  labelTokenDisabled,
  labelTokenEnabled,
  labelType,
  labelUser
} from '../../www/front_src/src/AuthenticationTokens/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from AuthenticationTokens/Specs/AuthenticationTokens.
const storyId = 'AuthenticationTokens/AuthenticationTokens/Default';
const tokensEndpoint = './api/latest/administration/tokens';

const searchesOf = (urls: Array<URL>): Array<unknown> =>
  urls
    .map((url) => url.searchParams.get('search'))
    .filter((search): search is string => Boolean(search))
    .map((search) => JSON.parse(search));

test.describe('Authentication tokens', () => {
  let listing: { urls: Array<URL> };

  test.beforeEach(async ({ mockApi }) => {
    listing = await mockApi({ endpoint: tokensEndpoint, response: listTokens });
    await mockApi({
      endpoint: './api/latest/configuration/users',
      response: listUsers
    });
  });

  test('lists the tokens', async ({ expectThemedScreenshot, mount }) => {
    const component = await mount(storyId);

    await expect(component.getByText('a-token')).toBeVisible();
    await expect(component.getByText('API').first()).toBeVisible();

    await expectThemedScreenshot(component, 'authentication-tokens');
  });

  test('reloads the listing on refresh', async ({ mount }) => {
    const component = await mount(storyId);
    await expect(component.getByText('a-token')).toBeVisible();
    const before = listing.urls.length;

    await component.getByTestId(labelRefresh).click();

    await expect.poll(() => listing.urls.length).toBeGreaterThan(before);
  });

  test('searches the tokens by name', async ({ mount }) => {
    const component = await mount(storyId);

    await component.getByPlaceholder(labelSearch).first().fill('token');

    await expect
      .poll(() => searchesOf(listing.urls))
      .toContainEqual({
        $and: [{ $or: [{ token_name: { $rg: 'token' } }] }, { $or: [] }]
      });
  });

  test('filters the tokens by name and status', async ({ mount, page }) => {
    const component = await mount(storyId);

    await component.getByTestId(labelFilters).click();
    // The filters live in a portalled popover.
    await page.getByTestId(labelName).nth(1).fill('token 1');
    await page.getByTestId(labelDisabled).click();
    await page.getByTestId(labelSearch).click();
    await component.getByTestId(labelFilters).click();

    await expect(page.getByTestId(labelSearch)).toHaveCount(0);
    await expect
      .poll(() => searchesOf(listing.urls))
      .toContainEqual({
        $and: [
          { $or: [{ token_name: { $rg: 'token 1' } }] },
          { $or: [{ is_revoked: { $eq: true } }] }
        ]
      });
  });

  test('deletes a token after confirmation', async ({
    mockApi,
    mount,
    page
  }) => {
    const deletion = await mockApi({
      endpoint: `${tokensEndpoint}/d-token/users/23`,
      method: 'DELETE',
      response: { code: 200, status: 'ok' }
    });
    const component = await mount(storyId);

    await component.getByTestId('Delete_d-token_23').click();
    await expect(page.getByText(labelDeleteToken).first()).toBeVisible();
    await page.getByTestId('confirm').click();

    await expect.poll(() => deletion.urls.length).toBe(1);
    await expect(page.getByText(labelTokenDeleted)).toBeVisible();
  });

  const toggles = [
    {
      confirmation: labelDisableToken,
      revoked: true,
      success: labelTokenDisabled,
      token: 'd-token'
    },
    {
      confirmation: labelEnableToken,
      revoked: false,
      success: labelTokenEnabled,
      token: 'e-token'
    }
  ];

  for (const { confirmation, revoked, success, token } of toggles) {
    test(`${revoked ? 'disables' : 'enables'} a token`, async ({
      mockApi,
      mount,
      page
    }) => {
      const toggle = await mockApi({
        endpoint: `${tokensEndpoint}/${token}/users/23`,
        method: 'PATCH',
        response: { code: 200, status: 'ok' }
      });
      const component = await mount(storyId);

      await component.getByTestId(`Enable/Disable_${token}_23`).click();
      await expect(page.getByText(confirmation).first()).toBeVisible();
      await page.getByTestId('confirm').click();

      await expect.poll(() => toggle.bodies).toEqual([{ is_revoked: revoked }]);
      await expect(page.getByText(success)).toBeVisible();
    });
  }

  test('copies a token', async ({ context, mockApi, mount }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    const details = await mockApi({
      endpoint: `${tokensEndpoint}/e-token/users/23`,
      response: tokenDetails
    });
    const component = await mount(storyId);

    await component.getByTestId('Copy_e-token_23').click();

    await expect.poll(() => details.urls.length).toBe(1);
  });

  const creations = [
    { hasSecurityWarning: true, hasUser: true, type: undefined },
    {
      hasSecurityWarning: false,
      hasUser: false,
      type: 'Centreon monitoring agent'
    },
    { hasSecurityWarning: false, hasUser: false, type: 'Poller' }
  ];

  for (const { hasSecurityWarning, hasUser, type } of creations) {
    test(`creates a ${type ?? 'API'} token`, async ({
      mockApi,
      mount,
      page
    }) => {
      const creation = await mockApi({
        endpoint: tokensEndpoint,
        method: 'POST',
        response: tokenDetails
      });
      const component = await mount(storyId);

      await component.getByTestId(labelAdd).click();
      const dialog = page.getByRole('dialog');
      await expect(
        dialog.getByText(labelCreateAuthenticationToken).first()
      ).toBeVisible();
      await expect(dialog.getByTestId('submit')).toBeDisabled();

      await dialog.getByTestId(labelName).nth(1).fill('token 1');
      if (type) {
        await dialog.getByTestId(labelType).click();
        await page.getByRole('option', { name: type }).click();
      }
      if (hasUser) {
        await dialog.getByTestId(labelUser).click();
        await page.getByRole('option', { name: 'admin' }).first().click();
      } else {
        await expect(dialog.getByTestId(labelUser)).toHaveCount(0);
      }
      await dialog.getByTestId('submit').click();

      await expect.poll(() => creation.urls.length).toBe(1);
      await expect(dialog.getByTestId('tokenInput')).toBeVisible();
      await expect(dialog.getByText(labelSecurityToken)).toHaveCount(
        hasSecurityWarning ? 1 : 0
      );
      await dialog.getByText(labelDone).click();
      await expect(page.getByText(labelCreateAuthenticationToken)).toHaveCount(
        0
      );
    });
  }
});

test.describe('Authentication tokens with a deleted creator', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: tokensEndpoint,
      response: listTokensWithDeletedCreator
    });
    await mockApi({
      endpoint: './api/latest/configuration/users',
      response: listUsers
    });
  });

  test('lists every token', async ({ mount }) => {
    const component = await mount(storyId);

    for (const token of ['a-token', 'b-token', 'c-token']) {
      await expect(component.getByText(token)).toBeVisible();
    }
    await expect(component.getByText('Deleted Creator').first()).toBeVisible();
  });

  test('does not offer the deleted creator as a filter', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId);

    await component.getByTestId(labelFilters).click();
    await page.getByTestId(labelCreator).click();

    await expect(page.getByRole('option', { name: 'Jane Doe' })).toBeAttached();
    await expect(
      page.getByRole('option', { name: 'Deleted Creator' })
    ).toHaveCount(0);
  });
});
