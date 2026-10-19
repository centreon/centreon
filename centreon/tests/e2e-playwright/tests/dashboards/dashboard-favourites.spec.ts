import { randomUUID } from 'node:crypto';

import { adminUser } from '../../fixtures/credentials';
import { expect, test } from '../../fixtures/test';
import { signIn } from '../../helpers/session';
import { DashboardsListPage } from '../../pages/DashboardsListPage';

/**
 * Replaces Dashboards/19: a favourite is kept for the user, and the
 * favourites filter lists it alone. The toggling itself is covered by the
 * component suite.
 */
const dashboard = (name: string) => ({
  description: null,
  name,
  panels: [],
  refresh: { interval: null, type: 'global' }
});

test('keeps the favourite dashboards of a user', async ({ adminApi, page }) => {
  const suffix = randomUUID().slice(0, 6);
  const favourite = `pw-favourite-${suffix}`;
  const other = `pw-other-${suffix}`;
  const ids = [
    await adminApi.createDashboard(dashboard(favourite)),
    await adminApi.createDashboard(dashboard(other))
  ];

  try {
    await signIn(page, adminUser);
    const library = new DashboardsListPage(page);
    await library.open();
    const favouriteButton = library
      .card(favourite)
      .getByRole('button', { name: 'FavoriteIconButton' });

    await favouriteButton.click();
    await expect(
      page.getByText('The dashboard has been added to your favorites.')
    ).toBeVisible();

    await test.step('The favourite survives a new visit', async () => {
      await library.open();
      await page
        .getByRole('checkbox', {
          name: 'Show only dashboards added to favorites'
        })
        .check();
      await library.expectVisible(favourite);
      await library.expectNotVisible(other);
    });

    await favouriteButton.click();
    await expect(
      page.getByText('The dashboard has been removed from your favorites.')
    ).toBeVisible();
    await library.expectNotVisible(favourite);
  } finally {
    for (const id of ids) {
      await adminApi
        .v2('DELETE', `/configuration/dashboards/${id}`)
        .catch(() => undefined);
    }
  }
});
