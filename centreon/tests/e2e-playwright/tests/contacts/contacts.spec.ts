import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { type ContactInput, ContactsPage } from '../../pages/ContactsPage';

/**
 * Adaptation of the Cypress `Contacts` feature, covering the reliable
 * single-admin core: create a contact through the legacy configuration form
 * and delete it from the listing.
 *
 * The non-admin / READ-ONLY / ACL-matrix, duplicate, and missing-required-field
 * error scenarios from the Cypress suite are out of scope here: they need
 * several provisioned ACL users + access groups and exercise legacy form
 * validation edge cases.
 *
 * Like the custom-views spec, the page is a legacy PHP page rendered in the
 * React shell's `#main-content` iframe, driven through a Playwright frame
 * locator.
 */
// DRAFT (workflow): ported from Cypress, not yet validated live — un-skip and fix selectors to finish.
test.describe.skip('Contacts', () => {
  test.use({ storageState: adminStorageStatePath });

  const contact: ContactInput = {
    alias: 'pw-contact-alias',
    email: 'pw-contact@example.com',
    name: 'pw-contact-name'
  };

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  // Best-effort cleanup so reruns stay idempotent even if the UI delete failed.
  test.afterEach(async ({ adminApi }) => {
    await adminApi
      .provision([{ action: 'DEL', object: 'CONTACT', values: contact.alias }])
      .catch(() => undefined);
  });

  test('creates a contact and deletes it', async ({ page }) => {
    const contacts = new ContactsPage(page);

    await contacts.open();

    await contacts.createContact(contact);
    await expect(contacts.contactLink(contact.alias)).toBeVisible();

    await contacts.deleteContact(contact.alias);
    await expect(contacts.contactLink(contact.alias)).toHaveCount(0);
  });
});
