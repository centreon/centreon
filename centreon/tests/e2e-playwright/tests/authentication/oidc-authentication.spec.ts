import { adminUser } from '../../fixtures/credentials';
import {
  oidcUser,
  oidcUserClaims,
  workingOidcProvider
} from '../../fixtures/oidc';
import {
  configureProvider,
  type ProviderSettings
} from '../../fixtures/providers';
import { expect, test } from '../../fixtures/test';
import type { CentreonApi } from '../../helpers/CentreonApi';
import { ensureStack, waitForHttpOk } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import {
  expectDenied,
  expectSignedIn,
  importTargets,
  inFreshBrowser,
  localContactFor,
  signInThroughKeycloak
} from '../../helpers/sso';
import { KeycloakLoginPage } from '../../pages/KeycloakLoginPage';
import { LoginPage } from '../../pages/LoginPage';
import { OidcConfigurationPage } from '../../pages/OidcConfigurationPage';

/**
 * Replaces Authentication/OpenID-connect 01 to 06. Each test sets the provider
 * through the API and restores it, so the tests do not depend on each other.
 * Requires the docker compose `openid` profile and the `sso` project, which
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

const oidcProvider = (adminApi: CentreonApi, settings: ProviderSettings = {}) =>
  configureProvider(adminApi, 'openid', {
    ...workingOidcProvider,
    ...settings
  });

const signInWithOidc = (page: Parameters<typeof expectSignedIn>[0]) =>
  signInThroughKeycloak(page, 'openid', oidcUser);

test('configures the provider from the form and keeps local sign-in in Mixed mode', async ({
  adminApi,
  browser,
  page
}) => {
  const provider = await oidcProvider(adminApi, { is_active: false });
  const user = await localContactFor(adminApi, oidcUser.login);
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
        await signInWithOidc(visitor);
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
  const user = await localContactFor(adminApi, oidcUser.login);
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
  const user = await localContactFor(adminApi, oidcUser.login);
  try {
    await test.step('A user outside the conditions is refused', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInWithOidc(visitor);
        await expectDenied(visitor);
      }));

    await test.step('The same user is let in once the conditions match', async () => {
      await oidcProvider(adminApi, conditions(oidcUser.login));
      await inFreshBrowser(browser, async (visitor) => {
        await signInWithOidc(visitor);
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
  const targets = await importTargets(adminApi, oidcUser.login);
  const endpoint = { custom_endpoint: null, type: 'introspection_endpoint' };
  const provider = await oidcProvider(adminApi, {
    auto_import: true,
    contact_template: targets.template,
    email_bind_attribute: 'email',
    fullname_bind_attribute: 'name',
    groups_mapping: {
      attribute_path: 'groups',
      endpoint,
      is_enabled: true,
      relations: [
        {
          contact_group_id: targets.contactGroup.id,
          group_value: oidcUserClaims.group
        }
      ]
    },
    roles_mapping: {
      apply_only_first_role: false,
      attribute_path: 'realm_access.roles',
      endpoint,
      is_enabled: true,
      relations: [
        {
          access_group_id: targets.aclGroups[0].id,
          claim_value: oidcUserClaims.role,
          priority: 1
        }
      ]
    }
  });
  try {
    await test.step('The unknown user signs in with the mapped menu access', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInWithOidc(visitor);
        await expectSignedIn(visitor);
      }));

    await test.step('Its contact comes from the claims and the template', () => {
      expect(targets.importedContact()).toEqual({
        email: oidcUserClaims.email,
        name: oidcUserClaims.name,
        template: targets.template.name
      });
    });

    await test.step('It belongs to the mapped ACL group and contact group', () => {
      expect(targets.memberships().sort()).toEqual(
        [targets.aclGroups[0].name, targets.contactGroup.name].sort()
      );
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(targets.teardown);
  }
});
