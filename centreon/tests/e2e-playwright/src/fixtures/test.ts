import { randomUUID } from 'node:crypto';

import { test as base } from '@playwright/test';

import { CentreonApi, type Contact } from '../api/CentreonApi';
import { anonymousStorageState } from '../config/environment';
import { AuthenticationPage } from '../pages/AuthenticationPage';
import { AuthenticationTokensPage } from '../pages/AuthenticationTokensPage';
import { LoginPage } from '../pages/LoginPage';
import { ResourcesStatusPage } from '../pages/ResourcesStatusPage';

export interface TestUser extends Contact {
  id: number;
}

interface Pages {
  /** Login page of a separate, logged out browser context. */
  anonymousLoginPage: LoginPage;
  authenticationPage: AuthenticationPage;
  authenticationTokensPage: AuthenticationTokensPage;
  loginPage: LoginPage;
  resourcesStatusPage: ResourcesStatusPage;
}

interface Data {
  /** Creates a local contact, deleted after the test. */
  createUser: (overrides?: Partial<Contact>) => Promise<TestUser>;
  /** Unique name, so specs running in parallel never share data. */
  uniqueName: (prefix: string) => string;
}

interface Workers {
  api: CentreonApi;
}

/**
 * Page objects are injected as fixtures: specs declare the pages they need and
 * never instantiate them, so a page object signature change stays local.
 */
export const test = base.extend<Pages & Data, Workers>({
  anonymousLoginPage: async ({ browser }, use) => {
    const context = await browser.newContext({
      storageState: anonymousStorageState
    });
    await use(new LoginPage(await context.newPage()));
    await context.close();
  },

  api: [
    // biome-ignore lint/correctness/noEmptyPattern: Playwright fixture signature
    async ({}, use) => {
      const api = await CentreonApi.create();
      await use(api);
      await api.dispose();
    },
    { scope: 'worker' }
  ],

  authenticationPage: async ({ page }, use) => {
    await use(new AuthenticationPage(page));
  },

  authenticationTokensPage: async ({ page }, use) => {
    await use(new AuthenticationTokensPage(page));
  },

  createUser: async ({ api, uniqueName }, use) => {
    const created: Array<string> = [];

    await use(async (overrides = {}) => {
      const contact: Contact = {
        alias: uniqueName('e2e-user'),
        password: 'Centreon!2021E2e',
        ...overrides
      };
      const id = await api.addContact(contact);
      created.push(contact.alias);

      return { ...contact, id };
    });

    for (const alias of created) {
      await api.deleteContact(alias);
    }
  },

  loginPage: async ({ page }, use) => {
    await use(new LoginPage(page));
  },

  resourcesStatusPage: async ({ page }, use) => {
    await use(new ResourcesStatusPage(page));
  },

  // biome-ignore lint/correctness/noEmptyPattern: Playwright fixture signature
  uniqueName: async ({}, use) => {
    await use((prefix) => `${prefix}-${randomUUID().slice(0, 8)}`);
  }
});

export { expect } from '@playwright/test';
