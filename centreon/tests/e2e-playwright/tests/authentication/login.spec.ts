import { anonymousStorageState } from '../../src/config/environment';
import { users } from '../../src/config/users';
import { expect, test } from '../../src/fixtures/test';

// Replaces features/Authentication/Login/01-login.feature.
test.describe('Login', { tag: '@REQ_MON-22032' }, () => {
  test.use({ storageState: anonymousStorageState });

  test('redirects anonymous users to the login page', async ({
    loginPage,
    page
  }) => {
    await page.goto('/centreon/');

    await loginPage.expectToBeDisplayed();
    await loginPage.expectFormToBeReady();
  });

  test('logs in with valid credentials and lands on the default page', {
    tag: '@MON-22033'
  }, async ({ createUser, loginPage, resourcesStatusPage }) => {
    const user = await createUser();

    await loginPage.goto();
    await loginPage.login(user);

    await resourcesStatusPage.expectToBeLoaded();
    await resourcesStatusPage.userMenu.expectConnectedAs(user.alias);
  });

  test('keeps the user on the login page when credentials are invalid', async ({
    loginPage
  }) => {
    await loginPage.goto();

    const response = await loginPage.login(users.unknown);

    await loginPage.expectAuthenticationFailed(response);
  });

  test('logs out from the user menu and goes back to the login page', {
    tag: '@MON-22034'
  }, async ({ createUser, loginPage, page, resourcesStatusPage }) => {
    // A dedicated user: logging out must not end the shared admin session.
    const user = await createUser();
    await loginPage.goto();
    await loginPage.login(user);
    await resourcesStatusPage.expectToBeLoaded();

    await resourcesStatusPage.userMenu.logout();

    await loginPage.expectToBeDisplayed();
    await page.goto(resourcesStatusPage.pathname);
    await loginPage.expectToBeDisplayed();
    await expect(loginPage.aliasField).toBeVisible();
  });
});
