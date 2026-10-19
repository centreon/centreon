import type { Page } from '@playwright/test';

import { adminUser } from '../../fixtures/credentials';
import {
  configureProvider,
  type ProviderSettings
} from '../../fixtures/providers';
import {
  samlForm,
  samlUser,
  samlUserClaims,
  workingSamlProvider
} from '../../fixtures/saml';
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
import { SamlConfigurationPage } from '../../pages/SamlConfigurationPage';

/**
 * Replaces Authentication/SAML 01 to 07, with the same independent setup as
 * the OpenID Connect tests (`sso` project, docker compose `openid` profile).
 */
test.beforeAll(async () => {
  test.setTimeout(600_000);
  await ensureStack({
    profiles: ['openid'],
    services: ['web', 'openid', 'sso-proxy']
  });
  await waitForHttpOk(
    'http://localhost:8080/realms/Centreon_SSO/protocol/saml/descriptor'
  );
});

const samlProvider = (adminApi: CentreonApi, settings: ProviderSettings = {}) =>
  configureProvider(adminApi, 'saml', { ...workingSamlProvider, ...settings });

const signInWithSaml = (page: Page) =>
  signInThroughKeycloak(page, 'SAML', samlUser);

test('configures the provider from the form and keeps local sign-in in Mixed mode', async ({
  adminApi,
  browser,
  page
}) => {
  const provider = await samlProvider(adminApi, { is_active: false });
  const user = await localContactFor(adminApi, samlUserClaims.alias);
  try {
    await test.step('The administrator enables a valid provider', async () => {
      await signIn(page, adminUser);
      const form = new SamlConfigurationPage(page);
      await form.open();
      await expect(form.mixedMode).toBeChecked();
      await form.enable(samlForm);
      await form.save();
    });

    await test.step('The realm user signs in through the provider', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInWithSaml(visitor);
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

test('sends users straight to the provider in SAML only mode', async ({
  adminApi,
  browser
}) => {
  const provider = await samlProvider(adminApi, { is_forced: true });
  const user = await localContactFor(adminApi, samlUserClaims.alias);
  try {
    await inFreshBrowser(browser, async (visitor) => {
      await visitor.goto('/centreon/');
      const keycloak = new KeycloakLoginPage(visitor);
      await keycloak.expectVisible();
      await keycloak.login(samlUser);
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
      attribute_path: samlForm.userIdAttribute,
      authorized_values: [authorized],
      is_enabled: true
    }
  });
  const provider = await samlProvider(
    adminApi,
    conditions('someone@example.test')
  );
  const user = await localContactFor(adminApi, samlUserClaims.alias);
  try {
    await test.step('A user outside the conditions is refused', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInWithSaml(visitor);
        await expectDenied(visitor);
      }));

    await test.step('The same user is let in once the conditions match', async () => {
      await samlProvider(adminApi, conditions(samlUserClaims.alias));
      await inFreshBrowser(browser, async (visitor) => {
        await signInWithSaml(visitor);
        await expectSignedIn(visitor);
      });
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(user.teardown);
  }
});

test('imports an unknown user into its template, first matching ACL group and contact groups', async ({
  adminApi,
  browser
}) => {
  const targets = await importTargets(adminApi, samlUserClaims.alias, 2);
  const [firstGroup, secondGroup] = targets.aclGroups;
  const provider = await samlProvider(adminApi, {
    auto_import: true,
    contact_template: targets.template,
    email_bind_attribute: samlForm.userIdAttribute,
    fullname_bind_attribute: samlUserClaims.firstNameAttribute,
    groups_mapping: {
      attribute_path: 'groups',
      is_enabled: true,
      relations: [
        {
          contact_group_id: targets.contactGroup.id,
          group_value: samlUserClaims.group
        }
      ]
    },
    roles_mapping: {
      apply_only_first_role: true,
      attribute_path: 'Role',
      is_enabled: true,
      relations: samlUserClaims.roles.map((role, index) => ({
        access_group_id: [firstGroup, secondGroup][index].id,
        claim_value: role,
        priority: index + 1
      }))
    }
  });
  try {
    await test.step('The unknown user signs in with the mapped menu access', () =>
      inFreshBrowser(browser, async (visitor) => {
        await signInWithSaml(visitor);
        await expectSignedIn(visitor);
      }));

    await test.step('Its contact comes from the attributes and the template', () => {
      expect(targets.importedContact()).toEqual({
        email: samlUserClaims.alias,
        name: samlUserClaims.firstName,
        template: targets.template.name
      });
    });

    await test.step('Only the first matching role gives an ACL group', () => {
      expect(targets.memberships().sort()).toEqual(
        [firstGroup.name, targets.contactGroup.name].sort()
      );
    });
  } finally {
    await provider.restore();
    await adminApi.cleanUp(targets.teardown);
  }
});
