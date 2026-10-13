import { expect, test as setup } from '@playwright/test';

import { adminStorageState, centreonBasePath } from '../src/config/environment';
import { users } from '../src/config/users';

// Logs the admin in once through API v2: specs reuse the session cookie
// instead of going through the login form.
setup('authenticate as admin', async ({ page }) => {
  const response = await page.request.post(
    `${centreonBasePath}/authentication/providers/configurations/local`,
    { data: { login: users.admin.alias, password: users.admin.password } }
  );
  await expect(response).toBeOK();

  await page.context().storageState({ path: adminStorageState });
});
