import { randomUUID } from 'node:crypto';

import { adminStorageStatePath } from '../../fixtures/auth';
import { okServiceName, resourcesHostName } from '../../fixtures/resources';
import { expect, test } from '../../fixtures/test';
import { ensureResourcesMonitored } from '../../helpers/provisioning';

/**
 * Replaces Services-configuration/13: an address in a check output or in a
 * comment becomes a link on the legacy monitoring pages.
 */
test.use({ storageState: adminStorageStatePath });

test.beforeAll(async ({ baseURL }) => {
  test.setTimeout(300_000);
  await ensureResourcesMonitored(baseURL ?? '');
});

test('turns the addresses of the outputs and comments into links', async ({
  adminApi,
  page
}) => {
  const link = `https://docs.centreon.com/pw-${randomUUID().slice(0, 6)}`;
  const { id, parentId } = await adminApi.getServiceResource(okServiceName);

  try {
    await test.step('An address in the check output', async () => {
      await adminApi.submitResults([
        {
          host: resourcesHostName,
          output: `OK see ${link}`,
          service: okServiceName,
          status: 'ok'
        }
      ]);
      await expect(async () => {
        await page.goto(
          `/centreon/main.get.php?p=20201&o=svcd&host_name=${resourcesHostName}&service_description=${okServiceName}`
        );
        await expect(
          page.locator(`a.linkified[href="${link}"]`).first()
        ).toBeVisible({
          timeout: 2_000
        });
      }).toPass({ intervals: [3_000], timeout: 60_000 });
    });

    await test.step('An address in a comment', async () => {
      await adminApi.v2(
        'POST',
        `/monitoring/hosts/${parentId}/services/${id}/comments`,
        {
          comment: `Read ${link}`
        }
      );
      await expect(async () => {
        await page.goto('/centreon/main.get.php?p=21002');
        await expect(page.locator(`a[href="${link}"]`).first()).toBeVisible({
          timeout: 2_000
        });
      }).toPass({ intervals: [3_000], timeout: 60_000 });
    });
  } finally {
    // Back to the output of the fixture.
    await adminApi.submitResults([
      {
        host: resourcesHostName,
        output: 'OK - submitted by Playwright',
        service: okServiceName,
        status: 'ok'
      }
    ]);
  }
});
