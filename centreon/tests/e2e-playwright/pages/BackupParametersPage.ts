import { expect, test } from '@playwright/test';

import { BasePage } from './BasePage';

/**
 * Page Object of the backup parameters
 * (`Administration > Parameters > Backup`).
 */
export class BackupParametersPage extends BasePage {
  // The legacy inputs are drawn over by their labels.
  private async tick(name: string, value?: string): Promise<void> {
    const input = this.page.locator(
      `input[name="${name}"]${value === undefined ? '' : `[value="${value}"]`}`
    );
    if (!(await input.isChecked())) {
      await input.dispatchEvent('click');
    }
    await expect(input).toBeChecked();
  }

  /** Back up both databases with a dump on `day` (0 is Sunday) and the files. */
  async enableFullBackup(day: number): Promise<void> {
    await test.step('Enable a full backup today', async () => {
      await this.goto('/centreon/main.get.php?p=50165&o=backup');
      await this.tick('backup_enabled[backup_enabled]', '1');
      await this.tick('backup_database_centreon');
      await this.tick('backup_database_centreon_storage');
      await this.tick('backup_database_type[backup_database_type]', '0');
      await this.tick(`backup_database_full[${day}]`);
      await this.tick('backup_configuration_files');
      await this.page
        .locator('input[name="backup_mysql_conf"]')
        .fill('/etc/my.cnf.d/container.cnf');
      await Promise.all([
        this.page.waitForEvent('load'),
        this.page.locator('input[name="submitC"]').first().click()
      ]);
    });
  }
}
