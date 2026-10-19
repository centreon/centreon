import { expect, type Page } from '@playwright/test';

import type { Credentials } from '../fixtures/credentials';

/**
 * Open a session for a user in the page context, through the login API: the
 * cookie lands in the context the page browses with. The login form itself is
 * covered by the authentication specs.
 */
export const signIn = async (
  page: Page,
  { login, password }: Credentials
): Promise<void> => {
  const response = await page.request.post(
    '/centreon/authentication/providers/configurations/local',
    { data: { login, password } }
  );
  expect(response.ok(), `sign in as ${login}`).toBeTruthy();
};
