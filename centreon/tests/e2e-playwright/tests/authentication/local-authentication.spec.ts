import { randomUUID } from 'node:crypto';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { expect, test } from '../../fixtures/test';
import type { CentreonApi } from '../../helpers/CentreonApi';
import { queryCentreonDb } from '../../helpers/docker';
import { signIn } from '../../helpers/session';
import { LoginPage } from '../../pages/LoginPage';
import { MyAccountPage } from '../../pages/MyAccountPage';

/**
 * Replaces Authentication/Login/02: the password security policy of the
 * local provider, enforced on real accounts. The default policy (12
 * characters, every character class, 5 attempts, one hour between changes,
 * 180 days of validity) is what the tests rely on; only the excluded users
 * are changed, and restored.
 */
const policyPath = '/administration/authentication/providers/local';

const user = (name: string): RestrictedUser =>
  restrictedUser({
    menus: [{ access: 'rw', topology: 'Administration;Parameters;My Account' }],
    name
  });

let users: Array<RestrictedUser> = [];

/** Date the current password of a user back by a number of days. */
const ageLocalPassword = (alias: string, days: number): void => {
  queryCentreonDb(
    `UPDATE contact_password cp JOIN contact c ON c.contact_id = cp.contact_id SET cp.creation_date = UNIX_TIMESTAMP() - ${days} * 86400 WHERE c.contact_alias = '${alias}'`
  );
};

test.afterEach(async ({ adminApi }) => {
  await adminApi.cleanUp(users.flatMap(({ teardown }) => teardown));
  users = [];
});

const newUser = async (
  adminApi: CentreonApi,
  name: string
): Promise<RestrictedUser> => {
  const created = user(`pw-local-${name}-${randomUUID().slice(0, 6)}`);
  users.push(created);
  await adminApi.provision(created.provisioning);

  return created;
};

test('applies the password policy to a password change', async ({
  adminApi,
  page
}) => {
  const account = await newUser(adminApi, 'change');
  const { password } = account.credentials;
  const strong = 'Pw!Strong-Passw0rd';
  const myAccount = new MyAccountPage(page);
  // A new account is within the delay between changes: date it back a day.
  ageLocalPassword(account.credentials.login, 1);
  await signIn(page, account.credentials);

  await test.step('A password outside the policy is refused', async () => {
    await myAccount.changePassword(password, 'azerty');
    await expect(myAccount.formError).toContainText(
      'Your password must be 12 characters long'
    );
  });

  await test.step('A password within the policy is accepted', async () => {
    await myAccount.changePassword(password, strong);
    const relogin = await page.request.post(
      '/centreon/authentication/providers/configurations/local',
      { data: { login: account.credentials.login, password: strong } }
    );
    expect(relogin.ok()).toBeTruthy();
  });

  await test.step('A second change within the delay is refused', async () => {
    await myAccount.changePassword(strong, `${strong}2`);
    await expect(myAccount.formError).toContainText(
      "You can't change your password because the delay before changing password is not over."
    );
  });
});

test('blocks an account after too many failed attempts', async ({
  adminApi,
  page
}) => {
  const account = await newUser(adminApi, 'blocked');
  const login = new LoginPage(page);

  for (let attempt = 0; attempt < 5; attempt += 1) {
    const response = await page.request.post(
      '/centreon/authentication/providers/configurations/local',
      { data: { login: account.credentials.login, password: 'wrong' } }
    );
    expect(response.status()).toBe(401);
  }

  await login.open();
  await login.login(account.credentials);
  await expect(login.errorMessage).toBeVisible();
  await expect(page).not.toHaveURL(/monitoring\/resources/);
});

test('expires old passwords except for the excluded users', async ({
  adminApi,
  page
}) => {
  const expired = await newUser(adminApi, 'expired');
  const excluded = await newUser(adminApi, 'excluded');
  const policy = await adminApi.v2<{
    password_security_policy: {
      password_expiration: { excluded_users: Array<string> };
    };
  }>('GET', policyPath);
  const original = structuredClone(policy);
  policy.password_security_policy.password_expiration.excluded_users.push(
    excluded.credentials.login
  );
  await adminApi.v2('PUT', policyPath, policy);

  try {
    // Age both passwords past the 180 days of validity.
    ageLocalPassword(expired.credentials.login, 200);
    ageLocalPassword(excluded.credentials.login, 200);
    const login = new LoginPage(page);

    await test.step('An expired password must be renewed', async () => {
      await login.open();
      await login.login(expired.credentials);
      await expect(page).toHaveURL(/reset-password/);
    });

    await test.step('An excluded user keeps signing in', async () => {
      await page.context().clearCookies();
      await signIn(page, excluded.credentials);
    });
  } finally {
    await adminApi.v2('PUT', policyPath, original);
  }
});
