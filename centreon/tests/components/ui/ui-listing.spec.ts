import { expect, test } from '../fixtures';

/**
 * packages/ui Listing: expandable sub items, their selection, the responsive
 * layout and the clickable columns.
 */
test.use({ viewport: { height: 800, width: 1280 } });

const story = 'ui/Listing/Listing';
const subItemNames = Array.from(
  { length: 10 },
  (_, index) => `Sub Item ${index * 10}`
);

test('expands and collapses the sub items of a row', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(`${story}/SubItems`);
  await expect(component.getByText('E0', { exact: true })).toBeVisible();
  for (const index of [0, 8]) {
    await component.getByLabel(`Expand ${index}`).click();
    await expect(component.getByLabel(`Collapse ${index}`)).toBeVisible();
    for (const name of subItemNames) {
      await expect(
        component.getByText(name, { exact: true }).first()
      ).toBeAttached();
      await expect(
        component
          .getByText(`${name.replace('Item', 'item')} description`)
          .first()
      ).toBeAttached();
    }
    if (index === 0) {
      await expectThemedScreenshot(component, 'listing-sub-items');
    }
    await component.getByLabel(`Collapse ${index}`).click();
    await expect(component.getByText('Sub item 10 description')).toHaveCount(0);
  }
});

test('highlights only the hovered sub item row', async ({ mount }) => {
  const component = await mount(`${story}/SubItems`, { small: true });
  await component.getByLabel('Expand 2').click();
  await component.getByLabel('Expand 0').click();
  await component.getByText('Sub Item 0', { exact: true }).first().hover();
  const hovered = component.locator('[data-is-hovered="true"]');
  await expect(hovered).toHaveCount(1);
  await expect(hovered).toContainText('Sub Item 0');
});

test('selects the sub items with their parent, all at once or with shift', async ({
  mount,
  page
}) => {
  const component = await mount(`${story}/SubItems`, {
    canCheckSubItems: true,
    small: true
  });
  await component.getByLabel('Expand 2').click();
  await component.getByLabel('Select row 2').first().click();
  await page.keyboard.down('Shift');
  await component.getByLabel('Select row 50').click();
  await page.keyboard.up('Shift');
  for (const id of [10, 20, 30, 40]) {
    await expect(component.getByLabel(`Select row ${id}`)).toBeChecked();
  }

  const all = await mount(`${story}/SubItems`, {
    canCheckSubItems: true,
    small: true
  });
  await all.getByLabel('Expand 2').click();
  await all.getByLabel('Select all').first().click();
  for (const id of [1, 2, 10, 20, 50, 90]) {
    await expect(all.getByLabel(`Select row ${id}`).first()).toBeChecked();
  }
});

for (const [variant, header] of [
  ['compact', '30px'],
  ['extended', '38px']
] as const) {
  test(`spreads the large column over several lines in ${variant} mode`, async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(`${story}/Responsive`, { variant });
    // The large column wraps: its rows are taller than a single line.
    const rowsTemplate = await component
      .locator('.MuiTable-root')
      .evaluate((table) => getComputedStyle(table).gridTemplateRows);
    const [headerRow, ...bodyRows] = rowsTemplate.split(' ');
    expect(headerRow).toBe(header);
    expect(bodyRows).toHaveLength(10);
    for (const row of bodyRows) {
      expect(Number.parseFloat(row)).toBeGreaterThan(50);
    }
    await expectThemedScreenshot(component, `listing-responsive-${variant}`);
  });
}

test('toggles a checkbox in a clickable column without clicking the row', async ({
  mount
}) => {
  const component = await mount(`${story}/ClickableColumn`);
  await component.getByLabel('Toggle E0').click();
  await expect(component.getByLabel('Toggle E0')).toBeChecked();
  await expect(component.getByTestId('row-clicks')).toHaveText('0');
});
