import { users } from '../../src/config/users';
import { expect, test } from '../../src/fixtures/test';

test.describe('Local authentication', () => {
  test('logs in with valid credentials and lands on the default page', async ({
    loginPage,
    resourcesStatusPage
  }) => {
    await loginPage.goto();
    await loginPage.expectFormToBeReady();

    await loginPage.login(users.admin);

    await resourcesStatusPage.expectToBeLoaded();
  });

  test('keeps the user on the login page when credentials are invalid', async ({
    loginPage
  }) => {
    await loginPage.goto();

    await loginPage.login(users.unknown);

    await expect(loginPage.errorMessage).toBeVisible();
    await loginPage.expectToBeDisplayed();
  });

  test('logs out from the user menu and goes back to the login page', async ({
    loginPage,
    resourcesStatusPage
  }) => {
    await loginPage.goto();
    await loginPage.login(users.admin);
    await resourcesStatusPage.expectToBeLoaded();

    await resourcesStatusPage.userMenu.logout();

    await loginPage.expectToBeDisplayed();
    await expect(loginPage.aliasField).toBeVisible();
  });
});
