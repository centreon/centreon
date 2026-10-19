import { randomUUID } from 'node:crypto';

import type { Browser, Page } from '@playwright/test';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import {
  configureProvider,
  type ProviderSettings
} from '../../fixtures/providers';
import { expect, test } from '../../fixtures/test';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';

/**
 * Replaces Authentication/Web-SSO 01 and 02. Cypress never signed in through
 * Web SSO (cross-origin limits); here the browser sends the login header the
 * front proxy would set, which PHP reads as `HTTP_X_PW_SSO_USER`.
 */
const loginHeader = 'X-Pw-Sso-User';

let user: RestrictedUser;
let restore: (() => Promise<void>) | undefined;

const webSso = async (
  adminApi: Parameters<typeof configureProvider>[0],
  settings: ProviderSettings = {}
): Promise<void> => {
  const provider = await configureProvider(adminApi, 'web-sso', {
    blacklist_client_addresses: [],
    is_active: true,
    is_forced: false,
    login_header_attribute: 'HTTP_X_PW_SSO_USER',
    pattern_matching_login: null,
    pattern_replace_login: null,
    trusted_client_addresses: [],
    ...settings
  });
  restore ??= provider.restore;
};

test.beforeEach(async ({ adminApi }) => {
  user = restrictedUser({
    menus: [{ access: 'rw', topology: 'Monitoring;Resources Status' }],
    name: `pw-websso-${randomUUID().slice(0, 6)}`
  });
  await adminApi.provision(user.provisioning);
});

test.afterEach(async ({ adminApi }) => {
  await restore?.();
  restore = undefined;
  await adminApi.cleanUp(user.teardown);
});

/** Open Centreon in a browser without session, sending the login header. */
const visit = async (
  browser: Browser,
  login: string | undefined,
  check: (page: Page) => Promise<void>
): Promise<void> => {
  const context = await browser.newContext({
    extraHTTPHeaders: login ? { [loginHeader]: login } : {}
  });
  try {
    const page = await context.newPage();
    await page.goto('/centreon/');
    await check(page);
  } finally {
    await context.close();
  }
};

const expectSignedIn = async (page: Page): Promise<void> => {
  await new MainHeader(page).expectLoaded();
  await expect(new LoginPage(page).aliasInput).toHaveCount(0);
};

test('signs in the user named by the header and keeps local sign-in in Mixed mode', async ({
  adminApi,
  browser
}) => {
  await webSso(adminApi);

  await test.step('The header signs its user in', () =>
    visit(browser, user.credentials.login, expectSignedIn));

  await test.step('Without the header, local users sign in with the form', () =>
    visit(browser, undefined, async (page) => {
      const login = new LoginPage(page);
      await login.expectVisible();
      await login.login(adminUser);
      await expectSignedIn(page);
    }));
});

test('extracts the login from the header with the matching pattern', async ({
  adminApi,
  browser
}) => {
  await webSso(adminApi, {
    pattern_matching_login: '@.*$',
    pattern_replace_login: ''
  });

  await visit(browser, `${user.credentials.login}@CORP.TEST`, expectSignedIn);
});

test('ignores the header of a client outside the trusted addresses', async ({
  adminApi,
  browser
}) => {
  await webSso(adminApi, { trusted_client_addresses: ['192.0.2.10'] });

  await visit(browser, user.credentials.login, async (page) => {
    await new LoginPage(page).expectVisible();
    await expect(new MainHeader(page).profileButton).toHaveCount(0);
  });
});
