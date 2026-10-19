import { randomUUID } from 'node:crypto';

import type { Browser, Page } from '@playwright/test';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { adminUser } from '../../fixtures/credentials';
import { oidcProvider, oidcUser, oidcUserClaims } from '../../fixtures/oidc';
import { expect, test } from '../../fixtures/test';
import type { ClapiAction } from '../../helpers/CentreonApi';
import {
  ensureStack,
  queryCentreonDb,
  waitForHttpOk
} from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { KeycloakLoginPage } from '../../pages/KeycloakLoginPage';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';
import { OidcConfigurationPage } from '../../pages/OidcConfigurationPage';

/**
 * Replaces Authentication/OpenID-connect 01 to 06. Each test sets the provider
 * through the API and restores it, so the tests do not depend on each other.
 * Requires the docker compose `openid` profile and the `oidc` project, which
 * maps `sso-proxy` to localhost in the browser.
 */
test.beforeAll(async () => {
  test.setTimeout(600_000);
  await ensureStack({
    profiles: ['openid'],
    services: ['web', 'openid', 'sso-proxy']
  });
  await waitForHttpOk(
    'http://localhost:8080/realms/Centreon_SSO/.well-known/openid-configuration'
  );
});

const menus = [
  { access: 'rw' as const, topology: 'Monitoring;Resources Status' }
];

/** The `oidc` realm user as a local contact who can open Resources Status. */
const localOidcContact = async (
  adminApi: Parameters<typeof oidcProvider>[0]
): Promise<RestrictedUser> => {
  const user = restrictedUser({ menus, name: oidcUser.login });
  await adminApi.cleanUp(user.teardown);
  await adminApi.provision(user.provisioning);

  return user;
};

/** Run a check in a browser without any Centreon or Keycloak session. */
const inFreshBrowser = async (
  browser: Browser,
  check: (page: Page) => Promise<void>
): Promise<void> => {
  const context = await browser.newContext();
  try {
    await check(await context.newPage());
  } finally {
    await context.close();
  }
};

/** Sign the realm user in from the Centreon login page. */
const signInThroughProvider = async (page: Page): Promise<void> => {
  const login = new LoginPage(page);
  await login.open();
  await login.loginWith('openid');
  const keycloak = new KeycloakLoginPage(page);
  await keycloak.expectVisible();
  await keycloak.login(oidcUser);
};

const expectSignedIn = async (page: Page): Promise<void> => {
  await expect(page).toHaveURL(/\/monitoring\/resources/, { timeout: 30_000 });
  await new MainHeader(page).expectLoaded();
};

test('configures the provider from the form and keeps local sign-in in Mixed mode', async ({
  adminApi,
  browser,
  page
}) => {
  const provider = await oidcProvider(adminApi, { is_active: false });
  const user = await localOidcContact(adminApi);
  try {
    await test.step('The administrator enables a valid provider', async () => {
      await signIn(page, adminUser);
      const form = new OidcConfigurationPage(page);
      await form.open();
      await form.expectMixedModeIsDefault();
      await form.fillProviderConfiguration();
      await form.enableOpenIdConnect();
      await form.save();
      await form.open();
      await form.expectClientSecretHidden();
    });

    await test.step('The realm user signs in through the provider', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInThroughProvider(visitor);
        await expectSignedIn(visitor);
      }));

    await test.step('A local user still signs in with the form', () =>
      inFreshBrowser(browser, async (visitor) => {
        const login = new LoginPage(visitor);
        await login.open();
        await login.login(adminUser);
        await expectSignedIn(visitor);
      }));
  } finally {
    await provider.restore();
    await adminApi.cleanUp(user.teardown);
  }
});

test('sends users straight to the provider in OpenID Connect only mode', async ({
  adminApi,
  browser
}) => {
  const provider = await oidcProvider(adminApi, { is_forced: true });
  const user = await localOidcContact(adminApi);
  try {
    await inFreshBrowser(browser, async (visitor) => {
      await visitor.goto('/centreon/');
      const keycloak = new KeycloakLoginPage(visitor);
      await keycloak.expectVisible();
      await keycloak.login(oidcUser);
      await expectSignedIn(visitor);
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(user.teardown);
  }
});

test('lets in only the users matching the authentication conditions', async ({
  adminApi,
  browser
}) => {
  const conditions = (authorized: string) => ({
    authentication_conditions: {
      attribute_path: 'preferred_username',
      authorized_values: [authorized],
      blacklist_client_addresses: [],
      endpoint: { custom_endpoint: null, type: 'introspection_endpoint' },
      is_enabled: true,
      trusted_client_addresses: []
    }
  });
  const provider = await oidcProvider(adminApi, conditions('someone-else'));
  const user = await localOidcContact(adminApi);
  try {
    await test.step('A user outside the conditions is refused', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInThroughProvider(visitor);
        await expect(visitor).toHaveURL(/\/authentication-denied/);
        await expect(
          visitor.getByText('You are not allowed to log in')
        ).toBeVisible();
      }));

    await test.step('The same user is let in once the conditions match', async () => {
      await oidcProvider(adminApi, conditions(oidcUser.login));
      await inFreshBrowser(browser, async (visitor) => {
        await signInThroughProvider(visitor);
        await expectSignedIn(visitor);
      });
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(user.teardown);
  }
});

test('imports an unknown user with its template, ACL groups and contact groups', async ({
  adminApi,
  browser
}) => {
  const suffix = randomUUID().slice(0, 6);
  const template = `pw-oidc-${suffix}-template`;
  const group = `pw-oidc-${suffix}-group`;
  const menu = `pw-oidc-${suffix}-menu`;
  const contactGroup = `pw-oidc-${suffix}-cg`;
  const objects: Array<ClapiAction> = [
    {
      action: 'ADD',
      object: 'CONTACTTPL',
      values: `${template};${template};;0;1;en_US;local`
    },
    { action: 'ADD', object: 'ACLGROUP', values: `${group};${group}` },
    { action: 'ADD', object: 'ACLMENU', values: `${menu};${menu}` },
    {
      action: 'GRANTRW',
      object: 'ACLMENU',
      values: `${menu};1;Monitoring;Resources Status`
    },
    { action: 'ADDMENU', object: 'ACLGROUP', values: `${group};${menu}` },
    { action: 'ADD', object: 'CG', values: `${contactGroup};${contactGroup}` }
  ];
  const teardown: Array<ClapiAction> = [
    { action: 'DEL', object: 'CONTACT', values: oidcUser.login },
    { action: 'DEL', object: 'CONTACTTPL', values: template },
    { action: 'DEL', object: 'ACLGROUP', values: group },
    { action: 'DEL', object: 'ACLMENU', values: menu },
    { action: 'DEL', object: 'CG', values: contactGroup }
  ];
  const id = (sql: string): number => Number(queryCentreonDb(sql).trim());
  await adminApi.cleanUp(teardown.slice(0, 1));
  await adminApi.provision(objects);

  const provider = await oidcProvider(adminApi, {
    auto_import: true,
    contact_template: {
      id: id(
        `SELECT contact_id FROM contact WHERE contact_name = '${template}'`
      ),
      name: template
    },
    email_bind_attribute: 'email',
    fullname_bind_attribute: 'name',
    groups_mapping: {
      attribute_path: 'groups',
      endpoint: { custom_endpoint: null, type: 'introspection_endpoint' },
      is_enabled: true,
      relations: [
        {
          contact_group_id: id(
            `SELECT cg_id FROM contactgroup WHERE cg_name = '${contactGroup}'`
          ),
          group_value: oidcUserClaims.group
        }
      ]
    },
    roles_mapping: {
      apply_only_first_role: false,
      attribute_path: 'realm_access.roles',
      endpoint: { custom_endpoint: null, type: 'introspection_endpoint' },
      is_enabled: true,
      relations: [
        {
          access_group_id: id(
            `SELECT acl_group_id FROM acl_groups WHERE acl_group_name = '${group}'`
          ),
          claim_value: oidcUserClaims.role,
          priority: 1
        }
      ]
    }
  });
  try {
    await test.step('The unknown user signs in with the mapped menu access', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInThroughProvider(visitor);
        await expectSignedIn(visitor);
      }));

    await test.step('Its contact comes from the claims and the template', () => {
      const [name, email, templateName] = queryCentreonDb(
        `SELECT c.contact_name, c.contact_email, t.contact_name FROM contact c JOIN contact t ON t.contact_id = c.contact_template_id WHERE c.contact_alias = '${oidcUser.login}'`
      )
        .trim()
        .split('\t');
      expect({ email, name, templateName }).toEqual({
        email: oidcUserClaims.email,
        name: oidcUserClaims.name,
        templateName: template
      });
    });

    await test.step('It belongs to the mapped ACL group and contact group', () => {
      const memberships = queryCentreonDb(
        `SELECT g.acl_group_name FROM acl_group_contacts_relations r JOIN acl_groups g ON g.acl_group_id = r.acl_group_id JOIN contact c ON c.contact_id = r.contact_contact_id WHERE c.contact_alias = '${oidcUser.login}' UNION SELECT cg.cg_name FROM contactgroup_contact_relation r JOIN contactgroup cg ON cg.cg_id = r.contactgroup_cg_id JOIN contact c ON c.contact_id = r.contact_contact_id WHERE c.contact_alias = '${oidcUser.login}'`
      )
        .trim()
        .split('\n');
      expect(memberships).toEqual(
        expect.arrayContaining([group, contactGroup])
      );
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(teardown);
  }
});
