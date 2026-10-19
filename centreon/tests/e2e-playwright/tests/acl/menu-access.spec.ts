import { randomUUID } from 'node:crypto';

import { restrictedUser } from '../../fixtures/acl';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * ACLs/03 only checked the menu access records; this checks what they are
 * for: the pages a user reaches and what it can do on them.
 */
const denial = 'You are not allowed to reach this page';

test('restricts a user to the pages of its menu access', async ({
  adminApi,
  page
}) => {
  const user = restrictedUser({
    menus: [
      { access: 'ro', topology: 'Monitoring;Event Logs' },
      { access: 'ro', topology: 'Configuration;Hosts' }
    ],
    name: `pw-menu-${randomUUID().slice(0, 6)}`
  });
  await adminApi.provision(user.provisioning);

  try {
    await signIn(page, user.credentials);

    await test.step('A granted page opens', async () => {
      await page.goto('/centreon/main.get.php?p=20301');
      await expect(page.locator('select#host_filter')).toBeAttached();
      await expect(page.getByText(denial)).toHaveCount(0);
    });

    await test.step('A page outside the menu access is refused', async () => {
      await page.goto('/centreon/main.get.php?p=60302');
      await expect(page.getByText(denial)).toBeVisible();
    });

    await test.step('A read-only page offers no write action', async () => {
      const hosts = new LegacyListingPage(page, '60101');
      await hosts.open();
      await expect(page.getByText(denial)).toHaveCount(0);
      await expect(hosts.addLink).toHaveCount(0);
    });
  } finally {
    await adminApi.cleanUp(user.teardown);
  }
});
