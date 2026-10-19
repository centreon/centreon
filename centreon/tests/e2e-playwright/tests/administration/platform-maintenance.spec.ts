import { adminStorageStatePath } from '../../fixtures/auth';
import { expect, test } from '../../fixtures/test';
import { execInWebContainer } from '../../helpers/docker';
import { BackupParametersPage } from '../../pages/BackupParametersPage';

/**
 * Replaces Backup-configuration/01 and Administration/04: the backup cron
 * dumps the databases on the full backup day, and the database page shows the
 * partitions and the state of the databases.
 */
test.use({ storageState: adminStorageStatePath });

test('dumps the databases on the full backup day', async ({ page }) => {
  test.setTimeout(240_000);
  const backups = '/var/cache/centreon/backup';
  execInWebContainer(`rm -f ${backups}/*.sql.gz`);
  await new BackupParametersPage(page).enableFullBackup(new Date().getDay());

  // The backup cron dates its files with the day of the container.
  const today = execInWebContainer('date +%Y-%m-%d').trim();
  execInWebContainer(
    '/usr/share/centreon/cron/centreon-backup.pl >/dev/null 2>&1 || true'
  );
  const files = execInWebContainer(`ls ${backups}`).trim().split('\n');
  expect(files).toEqual(
    expect.arrayContaining([
      `${today}-centreon.sql.gz`,
      `${today}-centreon_storage.sql.gz`
    ])
  );
});

test('shows the partitions and the state of the databases', async ({
  page
}) => {
  await page.goto('/centreon/main.get.php?p=50503');
  await expect(page.getByText('Partitioning Properties').first()).toBeVisible({
    timeout: 30_000
  });
  for (const table of [
    'data_bin',
    'logs',
    'log_archive_host',
    'log_archive_service'
  ]) {
    await expect(
      page.getByRole('link', { exact: true, name: table })
    ).toBeVisible();
  }
  await expect(page.locator('#database_informations')).toBeVisible();
  await expect(page.getByText('Database Engine').first()).toBeVisible();
  await expect(
    page.getByText('Centreon Databases Statistics').first()
  ).toBeVisible();
});
