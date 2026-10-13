import type { PasswordSecurityPolicy } from '../../src/api/CentreonApi';
import { test as base, expect } from '../../src/fixtures/test';

const test = base.extend<{ passwordPolicy: PasswordSecurityPolicy }>({
  // Snapshot of the platform-wide policy, restored whatever the outcome.
  passwordPolicy: async ({ api }, use) => {
    const initialPolicy = await api.getPasswordSecurityPolicy();
    await use(initialPolicy);
    await api.setPasswordSecurityPolicy(initialPolicy);
  }
});

// Replaces part of features/Authentication/Login/02-local-authentication.feature.
// The lock keeps these tests from running concurrently with any other test
// holding it, in any file or worker, without serial mode's skip-on-failure.
test.describe('Local authentication password policy', {
  lock: 'password-security-policy',
  tag: '@REQ_MON-22115'
}, () => {
  test('displays the default password policy', { tag: '@MON-22119' }, async ({
    authenticationPage,
    passwordPolicy
  }) => {
    expect(passwordPolicy.attempts).toBe(5);

    await authenticationPage.goto();

    await authenticationPage.expectTabToBeSelected('Password security policy');
    await authenticationPage.passwordSecurityPolicy.expectValues({
      blockingDuration: { days: '0', hours: '0', minutes: '15' },
      excludedUsers: ['centreon-gorgone'],
      expiration: { days: '27', months: '5' },
      minimumLength: '12',
      minimumTimeBetweenChanges: { days: '0', hours: '1' },
      numberOfAttempts: '5',
      requiredCases: [
        'lower case',
        'upper case',
        'numbers',
        'special characters'
      ]
    });
    await expect(
      authenticationPage.passwordSecurityPolicy.canReuseLastPasswords
    ).not.toBeChecked();
  });

  test('blocks a user after too many failed attempts', {
    tag: '@MON-22120'
  }, async ({
    anonymousLoginPage,
    authenticationPage,
    createUser,
    passwordPolicy
  }) => {
    expect(passwordPolicy.attempts).not.toBe(2);
    const user = await createUser({ admin: false });

    await authenticationPage.goto();
    await authenticationPage.passwordSecurityPolicy.setNumberOfAttempts(2);
    await authenticationPage.passwordSecurityPolicy.save();
    await authenticationPage.expectPasswordSecurityPolicySaved();

    await anonymousLoginPage.goto();
    for (let attempt = 1; attempt <= 2; attempt += 1) {
      await anonymousLoginPage.expectAuthenticationFailed(
        await anonymousLoginPage.login({ ...user, password: 'Wrong!Password1' })
      );
    }

    // Valid credentials are refused until the blocking duration expires.
    await anonymousLoginPage.expectAuthenticationFailed(
      await anonymousLoginPage.login(user)
    );
  });
});
