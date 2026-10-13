import type { TokenDuration } from '../../src/components/CreateTokenDialog';
import { test as base, expect, type TestUser } from '../../src/fixtures/test';

interface Token {
  name: string;
  user: TestUser;
}

const test = base.extend<{ existingToken: Token; newToken: Token }>({
  // Token seeded through the API before the test.
  existingToken: async ({ api, createUser, uniqueName }, use) => {
    const user = await createUser();
    const name = uniqueName('token');
    await api.createToken({ expirationInDays: 30, name, userId: user.id });

    await use({ name, user });

    await api.deleteToken(name, user.id);
  },

  // Token the test creates through the UI, deleted whatever the outcome.
  newToken: async ({ api, createUser, uniqueName }, use) => {
    const user = await createUser();
    const name = uniqueName('token');

    await use({ name, user });

    await api.deleteToken(name, user.id);
  }
});

// Replaces features/Api-Token/01-api-token-create.feature.
test.describe('Authentication token creation', {
  tag: '@REQ_MON-21276'
}, () => {
  const durations: Array<TokenDuration> = ['30 days', '60 days', '90 days'];

  for (const duration of durations) {
    test(`creates a token valid for ${duration}`, {
      tag: '@MON-36696'
    }, async ({ authenticationTokensPage, newToken }) => {
      await authenticationTokensPage.goto();
      const dialog = await authenticationTokensPage.openCreateTokenDialog();
      await dialog.fill({
        duration,
        name: newToken.name,
        user: newToken.user.alias
      });
      await dialog.generate();

      await dialog.expectTokenToBeGenerated();
      await dialog.expectTokenToBeMasked(true);
      await dialog.close();
      await authenticationTokensPage.expectTokenToBeListed(newToken.name);
    });
  }

  test('reveals and copies the generated token', { tag: '@MON-36694' }, async ({
    authenticationTokensPage,
    context,
    newToken,
    page
  }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);

    await authenticationTokensPage.goto();
    const dialog = await authenticationTokensPage.openCreateTokenDialog();
    await dialog.fill({
      duration: '7 days',
      name: newToken.name,
      user: newToken.user.alias
    });
    await dialog.generate();
    await dialog.expectTokenToBeGenerated();

    await dialog.toggleTokenVisibilityButton.click();
    await dialog.expectTokenToBeMasked(false);

    await dialog.copyTokenButton.click();
    await authenticationTokensPage.expectTokenCopied();
    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    expect(clipboard).toBe(await dialog.tokenField.inputValue());
  });
});

// Replaces features/Api-Token/04-api-token-delete.feature.
test.describe('Authentication token deletion', {
  tag: '@REQ_MON-21278'
}, () => {
  test('deletes a token after confirmation', { tag: '@MON-36703' }, async ({
    authenticationTokensPage,
    existingToken
  }) => {
    await authenticationTokensPage.goto();

    const confirmation = await authenticationTokensPage.requestDeletion(
      existingToken.name
    );
    await confirmation.confirm();

    await confirmation.expectToBeClosed();
    await authenticationTokensPage.expectTokenToBeListed(
      existingToken.name,
      false
    );
  });

  test('keeps the token when the deletion is cancelled', {
    tag: '@MON-38499'
  }, async ({ authenticationTokensPage, existingToken }) => {
    await authenticationTokensPage.goto();

    const confirmation = await authenticationTokensPage.requestDeletion(
      existingToken.name
    );
    await confirmation.cancel();

    await confirmation.expectToBeClosed();
    await authenticationTokensPage.expectTokenToBeListed(existingToken.name);
  });
});
