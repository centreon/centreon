import { randomUUID } from 'node:crypto';

import { adminUser, ldapUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import type { ClapiAction } from '../../helpers/CentreonApi';
import { ensureStack } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { LdapConfigurationPage, LdapImportPage } from '../../pages/LdapPages';
import { LegacyListingPage } from '../../pages/LegacyListingPage';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';

/**
 * Replaces Ldaps/02: a directory user imported by hand signs in with its
 * directory password, and its contact keeps its DN and has no local password.
 * The `openldap` configuration ships with the image; the test enables it and
 * restores it.
 */
const ldapConfiguration = 'openldap';
const ldapDn = `cn=${ldapUser.login},ou=people,ou=users,dc=centreon,dc=com`;

test.beforeAll(async () => {
  test.setTimeout(300_000);
  await ensureStack({ profiles: ['openldap'], services: ['openldap'] });
});

test('imports a directory user who signs in with the directory password', async ({
  adminApi,
  browser,
  page
}) => {
  const suffix = randomUUID().slice(0, 6);
  const group = `pw-ldap-${suffix}-group`;
  const menu = `pw-ldap-${suffix}-menu`;
  const ldap = new LdapConfigurationPage(page);
  const access: Array<ClapiAction> = [
    { action: 'ADD', object: 'ACLGROUP', values: `${group};${group}` },
    { action: 'ADD', object: 'ACLMENU', values: `${menu};${menu}` },
    {
      action: 'GRANTRW',
      object: 'ACLMENU',
      values: `${menu};1;Configuration;Users`
    },
    { action: 'ADDMENU', object: 'ACLGROUP', values: `${group};${menu}` },
    {
      action: 'ADDCONTACT',
      object: 'ACLGROUP',
      values: `${group};${ldapUser.login}`
    },
    { action: 'RELOAD', object: 'ACL', values: '' }
  ];
  await signIn(page, adminUser);

  try {
    await ldap.configure(ldapConfiguration, {
      autoImport: false,
      enabled: true
    });

    await test.step('The directory search handles accents', async () => {
      const directory = new LdapImportPage(page);
      await directory.search(
        `(&(uid=${ldapUser.login})(objectClass=posixAccount))`
      );
      await expect(directory.results).toHaveCount(1);
      await expect(directory.results.first()).toContainText(ldapDn);
      await directory.importAll();
      await expect(
        page.getByRole('link', { exact: true, name: ldapUser.login }).first()
      ).toBeVisible();
    });

    await adminApi.provision(access);

    const context = await browser.newContext();
    try {
      const ldapPage = await context.newPage();
      await test.step('The user signs in with its directory password', async () => {
        const login = new LoginPage(ldapPage);
        await login.open();
        await login.login(ldapUser);
        await new MainHeader(ldapPage).expectLoaded();
      });

      await test.step('Its contact keeps its DN and has no local password', async () => {
        const contacts = new LegacyListingPage(ldapPage, '60301');
        await contacts.open(ldapUser.login);
        await contacts.openForm(ldapUser.login);
        await expect(ldapPage.locator('input#contact_ldap_dn')).toHaveAttribute(
          'type',
          'hidden'
        );
        await expect(ldapPage.locator('input#passwd1')).toHaveCount(0);
      });
    } finally {
      await context.close();
    }
  } finally {
    await adminApi.cleanUp([
      { action: 'DEL', object: 'CONTACT', values: ldapUser.login },
      { action: 'DEL', object: 'ACLGROUP', values: group },
      { action: 'DEL', object: 'ACLMENU', values: menu }
    ]);
    await ldap.configure(ldapConfiguration, {
      autoImport: false,
      enabled: false
    });
  }
});
