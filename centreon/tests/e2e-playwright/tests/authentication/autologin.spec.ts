import { randomUUID } from 'node:crypto';

import type { Browser, Page } from '@playwright/test';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { CentreonUiParametersPage } from '../../pages/CentreonUiParametersPage';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';
import { MyAccountPage } from '../../pages/MyAccountPage';

/**
 * Replaces Authentication/Autologin/01: a user generates an autologin key,
 * copies the link from the profile menu, and the link signs a browser in
 * while the platform allows it, and only then.
 */
let user: RestrictedUser;
let parameters: CentreonUiParametersPage;
let wasEnabled: boolean;
let closeAdmin: () => Promise<void>;

test.beforeEach(async ({ adminApi, browser }) => {
  user = restrictedUser({
    menus: [
      { access: 'rw', topology: 'Administration;Parameters;My Account' },
      { access: 'ro', topology: 'Monitoring;Resources Status' }
    ],
    name: `pw-autologin-${randomUUID().slice(0, 6)}`
  });
  await adminApi.provision(user.provisioning);
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await signIn(adminPage, adminUser);
  parameters = new CentreonUiParametersPage(adminPage);
  wasEnabled = await parameters.isAutologinEnabled();
  await parameters.setAutologin(true);
  closeAdmin = () => adminContext.close();
});

test.afterEach(async ({ adminApi }) => {
  await parameters.setAutologin(wasEnabled);
  await closeAdmin();
  await adminApi.cleanUp(user.teardown);
});

/** The autologin link offered on a page, once the user has a key. */
const linkFrom = async (page: Page, path: string): Promise<string> => {
  await signIn(page, user.credentials);
  await new MyAccountPage(page).generateAutologinKey();
  await page.goto(path);
  const link = await new MainHeader(page).autologinLink();
  expect(link).toContain(`useralias=${user.credentials.login}`);

  return link;
};

/** Open a link in a browser without any session. */
const visit = async (
  browser: Browser,
  link: string,
  check: (visitor: Page) => Promise<void>
): Promise<void> => {
  const context = await browser.newContext();
  try {
    const visitor = await context.newPage();
    await visitor.goto(link);
    await check(visitor);
  } finally {
    await context.close();
  }
};

test('signs a browser in with an autologin link while the platform allows it', async ({
  browser,
  page
}) => {
  const link = await linkFrom(page, '/centreon/main.php?p=50104&o=c');

  await test.step('The link signs a fresh browser in', () =>
    visit(browser, link, async (visitor) => {
      await new MainHeader(visitor).expectLoaded();
      await expect(new LoginPage(visitor).aliasInput).toHaveCount(0);
    }));

  await test.step('The link is refused once autologin is disabled', async () => {
    await parameters.setAutologin(false);
    await visit(browser, link, (visitor) =>
      new LoginPage(visitor).expectVisible()
    );
  });
});

test('signs a browser in with the link copied from a React page', async ({
  browser,
  page
}) => {
  // Known bug: the link copied on a React page (here Resources Status) leads
  // to the login page; the same key works on a main.php link.
  test.fail();
  const link = await linkFrom(page, '/centreon/monitoring/resources');

  await visit(browser, link, async (visitor) => {
    await expect(visitor).toHaveURL(/monitoring\/resources/);
    await new MainHeader(visitor).expectLoaded();
  });
});
