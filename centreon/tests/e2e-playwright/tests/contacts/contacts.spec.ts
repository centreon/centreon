import { randomUUID } from 'node:crypto';

import type { Browser } from '@playwright/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { LegacyListingPage } from '../../pages/LegacyListingPage';

/**
 * Replaces Contacts/01, 04 and 05: a non-admin contact created from the form
 * signs in, still does once its alias carries an accent, and so does its
 * duplicate after the original is deleted.
 */
test.use({ storageState: adminStorageStatePath });

const password = 'Centreon!2021User';

/** Whether these credentials open a session, in a browser of their own. */
const signsIn = async (browser: Browser, login: string): Promise<boolean> => {
  // Explicitly empty: contexts would inherit the admin session otherwise.
  const context = await browser.newContext({
    storageState: { cookies: [], origins: [] }
  });
  try {
    const response = await context.request.post(
      '/centreon/authentication/providers/configurations/local',
      { data: { login, password } }
    );

    return response.ok();
  } finally {
    await context.close();
  }
};

test('creates a contact who signs in, with an accented alias and as a duplicate', async ({
  adminApi,
  browser,
  page
}) => {
  const name = `pw-contact-${randomUUID().slice(0, 6)}`;
  const accented = `${name}-é`;
  const contacts = new LegacyListingPage(page, '60301');

  try {
    await test.step('The admin creates a non-admin contact', async () => {
      await page.goto('/centreon/main.get.php?p=60301&o=a');
      for (const [field, value] of [
        ['contact_name', name],
        ['contact_alias', name],
        ['contact_email', `${name}@centreon.test`]
      ]) {
        await contacts.field(field).fill(value);
      }
      await page.getByText('Centreon Authentication', { exact: true }).click();
      await contacts.field('contact_passwd').fill(password);
      await contacts.field('contact_passwd2').fill(password);
      await contacts.pickRadio('contact_oreon[contact_oreon]', '1');
      await contacts.pickRadio('contact_admin[contact_admin]', '0');
      await page.locator('#validForm input[name^="submit"]').first().click();
      await contacts.open(name);
      await expect(contacts.row(name)).toBeVisible();
    });
    expect(await signsIn(browser, name)).toBe(true);

    await test.step('An accented alias still signs in', async () => {
      await contacts.edit(name, { contact_alias: accented });
      expect(await signsIn(browser, accented)).toBe(true);
      expect(await signsIn(browser, name)).toBe(false);
    });

    await test.step('Its duplicate signs in once the original is deleted', async () => {
      await contacts.open(name);
      await contacts.bulk('Duplicate', [name]);
      await contacts.open(name);
      await contacts.bulk('Delete', [name]);
      await expect(contacts.row(name)).toHaveCount(0);
      await expect(contacts.row(`${name}_1`)).toBeVisible();
      expect(await signsIn(browser, accented)).toBe(false);
      expect(await signsIn(browser, `${accented}_1`)).toBe(true);
    });
  } finally {
    await adminApi.cleanUp(
      [name, `${name}_1`, accented, `${accented}_1`].map((values) => ({
        action: 'DEL',
        object: 'CONTACT',
        values
      }))
    );
  }
});
