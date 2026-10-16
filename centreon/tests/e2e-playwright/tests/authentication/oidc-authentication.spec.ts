import { type Page } from '@playwright/test';

import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import {
  oidcContactActions,
  oidcUser,
  providerAclActions
} from '../../fixtures/oidc';
import { CentreonApi } from '../../helpers/CentreonApi';
import { ensureStack, waitForHttpOk } from '../../helpers/docker';
import { KeycloakLoginPage } from '../../pages/KeycloakLoginPage';
import { LoginPage } from '../../pages/LoginPage';
import { MainHeader } from '../../pages/MainHeader';
import { OidcConfigurationPage } from '../../pages/OidcConfigurationPage';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

/**
 * OpenID Connect authentication (migration of Cypress feature 01).
 *
 * The scenarios share platform state (the configured provider), so they run
 * serially: scenario 1 configures the provider, scenario 3 enables it and logs
 * in through Keycloak. Requires the docker compose `openid` profile and the
 * `oidc` Playwright project (which maps `sso-proxy` to localhost in the browser).
 */
test.describe
  .serial('OpenID Connect authentication', () => {
    test.beforeAll(async () => {
      // Ensure the docker compose `openid` profile is up (Keycloak + sso-proxy),
      // starting it if the running stack does not include it, then wait for
      // Keycloak to answer before provisioning.
      await ensureStack({
        profiles: ['openid'],
        services: ['web', 'openid', 'sso-proxy']
      });
      await waitForHttpOk(
        'http://localhost:8080/realms/Centreon_SSO/.well-known/openid-configuration'
      );

      // Provision the ACL group/menu and the local OIDC contact via CLAPI.
      const api = await CentreonApi.create(baseURL);
      try {
        const token = await api.authenticateV1(adminUser);
        for (const actions of [providerAclActions, oidcContactActions]) {
          try {
            await api.runClapiActions(token, actions);
          } catch (error) {
            console.warn(
              `[oidc setup] partial provisioning: ${(error as Error).message}`
            );
          }
        }
      } finally {
        await api.dispose();
      }
    });

    const loginAsAdmin = async (page: Page) => {
      const loginPage = new LoginPage(page);
      await loginPage.open();
      await loginPage.login(adminUser);
      await expect(page).toHaveURL(/\/monitoring\/resources/);
    };

    test('saves a valid provider configuration with hidden secrets', async ({
      page
    }) => {
      await loginAsAdmin(page);

      const oidcPage = new OidcConfigurationPage(page);
      await oidcPage.open();
      await oidcPage.fillProviderConfiguration();
      await oidcPage.save();

      await oidcPage.expectClientSecretHidden();

      await new MainHeader(page).logout();
      await new LoginPage(page).expectVisible();
    });

    test('keeps Mixed as the default mode and allows local authentication', async ({
      page
    }) => {
      await loginAsAdmin(page);

      const oidcPage = new OidcConfigurationPage(page);
      await oidcPage.open();
      await oidcPage.expectMixedModeIsDefault();

      await new MainHeader(page).logout();

      const loginPage = new LoginPage(page);
      await loginPage.expectVisible();
      await loginPage.login(oidcUser);
      // Local login succeeds: the login form unmounts.
      await loginPage.aliasInput.waitFor({ state: 'detached' });
    });

    test('authenticates an end user through the OpenID provider', async ({
      page
    }) => {
      await loginAsAdmin(page);

      const oidcPage = new OidcConfigurationPage(page);
      await oidcPage.open();
      await oidcPage.enableOpenIdConnect();
      await oidcPage.save();
      await new MainHeader(page).logout();

      const loginPage = new LoginPage(page);
      await loginPage.expectVisible();
      await loginPage.loginWith('openid');

      const keycloak = new KeycloakLoginPage(page);
      await keycloak.expectVisible();
      await keycloak.login(oidcUser);

      await expect(page).toHaveURL(/\/monitoring\/resources/);
    });
  });
