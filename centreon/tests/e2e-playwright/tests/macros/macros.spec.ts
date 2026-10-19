import { adminStorageStatePath } from '../../fixtures/auth';
import { test } from '../../fixtures/test';
import { ensureStack } from '../../helpers/docker';
import { type Macro, MacrosPage } from '../../pages/MacrosPage';

/**
 * Adaptation of the Cypress `Macros` feature (`features/Macros/`), porting the
 * reliable single-user happy path of the host-macros suite: create a host that
 * carries one normal macro and one password macro, then re-open it and verify
 * the macros were persisted (the password macro masked).
 *
 * Out of scope for this slice (matching the migration brief): the ACL /
 * non-admin user setup (the original logs in as `user-non-admin-for-AC`), the
 * exported-engine-cfg-file assertions, host/service template inheritance and
 * macro override/highlight scenarios, and update/delete flows.
 *
 * Like {@link CustomViewsPage}, the host form is a legacy PHP page rendered in
 * the React shell's `#main-content` iframe, driven through a Playwright frame
 * locator. The original ran as a non-admin ACL user; here we use the shared
 * admin session, which is sufficient for the create-with-macros happy path.
 */
test.describe('Host macros', () => {
  test.use({ storageState: adminStorageStatePath });

  const hostName = 'pw-host-with-macros';
  const normalMacro: Macro = { name: 'PWMACRO1', value: 'normal' };
  const passwordMacro: Macro = { name: 'PWMACRO2', value: 'password' };

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  // Best-effort cleanup so reruns stay idempotent: the host is created through
  // the UI, so it is removed via CLAPI rather than a UI delete flow.
  test.afterEach(async ({ adminApi }) => {
    await adminApi
      .provision([{ action: 'DEL', object: 'HOST', values: hostName }])
      .catch(() => {
        /* host may not have been created if the test failed early */
      });
  });

  test('creates a host with a normal and a password macro', async ({
    page
  }) => {
    const macros = new MacrosPage(page);

    await macros.open();
    await macros.clickAddHost();
    await macros.fillHostBasics(hostName, hostName, '127.0.0.1');
    await macros.addNormalAndPasswordMacros(normalMacro, passwordMacro);
    await macros.save();

    await macros.openHostForEditing(hostName);
    await macros.expectSavedMacros(normalMacro, passwordMacro);
  });
});
