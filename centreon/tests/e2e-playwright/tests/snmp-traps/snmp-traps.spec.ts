import { expect, test } from '../../fixtures/test';

import { adminStorageStatePath } from '../../fixtures/auth';
import { ensureStack } from '../../helpers/docker';
import { SnmpTrapsPage } from '../../pages/SnmpTrapsPage';

/**
 * Migration of the Cypress `Snmp-Traps/01-traps-snmp-configuration` feature
 * (create + delete happy paths). Another legacy PHP page driven through a
 * Playwright frame locator.
 *
 * The modify/duplicate scenarios are out of scope for this slice (they pull in
 * hosts/services/templates and many advanced fields). A single lifecycle test
 * (create -> assert listed -> delete -> assert gone) keeps the run idempotent.
 */
test.describe('SNMP traps configuration', () => {
  test.use({ storageState: adminStorageStatePath });

  test.beforeAll(async () => {
    await ensureStack({ services: ['web'] });
  });

  test('creates an SNMP trap then deletes it', async ({ page }) => {
    const traps = new SnmpTrapsPage(page);
    const trapName = 'pw-snmp-trap';

    await traps.open();
    await traps.createTrap({
      name: trapName,
      oid: '1.2.3',
      output: 'trapOutputMessage',
      regexp: '/ruleRegexp/',
      ruleString: '@trapRule@',
      severity: 'Critical',
      vendor: 'Cisco'
    });

    await expect(traps.trapLink(trapName)).toBeVisible();

    await traps.deleteTrap(trapName);
    await expect(traps.trapLink(trapName)).toHaveCount(0);
  });
});
