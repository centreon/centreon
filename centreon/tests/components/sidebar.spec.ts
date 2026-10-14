import {
  labelCentreonLogo,
  labelMiniCentreonLogo
} from '../../www/front_src/src/Navigation/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Navigation/Sidebar/Sidebar; the Cypress light/dark describe
// loop becomes expectThemedScreenshot().
const storyId = 'Sidebar/Sidebar/Default';

test('displays the collapsed menu with an icon per item', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(storyId);

  await expect(component.getByAltText(labelMiniCentreonLogo)).toBeVisible();
  for (const item of await component.getByRole('listitem').all()) {
    await expect(item.locator('svg').first()).toBeVisible();
  }

  await expectThemedScreenshot(page, 'sidebar-collapsed');
});

test('expands the menu when the logo is clicked', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(storyId);

  await component.getByLabel(labelMiniCentreonLogo).click();

  await expect(component.getByAltText(labelCentreonLogo)).toBeVisible();
  const items = component.getByRole('listitem');
  await expect(items.nth(0)).toContainText('Monitoring');
  await expect(items.nth(1)).toContainText('Home');
  await expect(items.nth(2)).toContainText('Configuration');

  await expectThemedScreenshot(page, 'sidebar-expanded');
});

test('opens the children of a hovered item', async ({ mount }) => {
  const component = await mount(storyId);

  await component.getByRole('listitem').nth(2).hover();

  await expect(component.locator('[data-cy=collapse]').first()).toBeVisible();
});

test('opens nested children from an expanded menu', async ({ mount }) => {
  const component = await mount(storyId);

  await component.getByLabel(labelMiniCentreonLogo).click();
  await component.getByRole('listitem').nth(2).hover();

  const collapse = component.locator('[data-cy=collapse]').first();
  await expect(collapse).toBeVisible();
  await expect(component.getByTestId('ExpandMoreIcon').first()).toBeVisible();

  const firstGroup = collapse.locator('ul').first();
  await firstGroup.hover();

  await expect(firstGroup.locator('[data-cy=collapse]').first()).toBeVisible();
});

test('displays the additional label of an item', async ({ mount, page }) => {
  const component = await mount('Sidebar/Sidebar/WithAdditionalLabel');

  await component.getByRole('listitem').first().hover();

  await expect(page.getByText('Resources Status')).toBeVisible();
  await expect(page.getByText('BETA')).toBeVisible();
});
