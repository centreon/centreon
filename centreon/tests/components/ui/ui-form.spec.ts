import { expect, moveSortable, test } from '../fixtures';

/**
 * packages/ui Form: the sortable list input, the file input and the
 * collapsible sections.
 */
test.describe('List input', () => {
  const list = 'ui/Form/Form/List';
  // faker.seed(42): the first two items the story adds.
  const first = 'Lavinia (Wiegand LLC)';

  test('adds, sorts and removes items', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(list);
    await expect(component.getByText('Add an item to the list')).toBeVisible();
    await expect(component.getByText('Sort items')).toBeVisible();

    await component.getByRole('button', { name: 'Add item' }).click();
    await expect(component.getByLabel('sort-0')).toBeVisible();
    await expect(component.getByLabel('delete-0')).toBeVisible();
    await expect(component.getByText(first)).toBeVisible();
    await expectThemedScreenshot(component, 'form-list');

    await component.getByRole('button', { name: 'Add item' }).click();
    await expect(component.getByLabel('sort-1')).toBeVisible();
    const items = component.getByText(/^\S+ \(.+\)$/);
    const second = (await items.nth(1).textContent()) ?? '';
    await expect(items).toHaveText([first, second]);

    await moveSortable(page, component.getByLabel('sort-0'), 'ArrowDown');
    await expect(items).toHaveText([second, first]);

    // The labels follow the ids of the items, not their positions.
    await component.getByLabel('delete-0').click();
    await expect(items).toHaveText([second]);
  });
});

test('selects a file', async ({ mount }) => {
  const component = await mount('ui/Form/Form/File');
  await expect(component.getByText('Drop or select a file')).toBeVisible();
  await component.getByLabel('select a file').setInputFiles('package.json');
  await expect(component.getByText('package.json')).toBeVisible();
});

test('orders the sections and scrolls to the one clicked', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount('ui/Form/Form/Sections');
  const groups = ['First group', 'Second group', 'Third group', 'Fourth group'];
  for (const group of groups) {
    await expect(
      component.getByText(group, { exact: true }).first()
    ).toBeVisible();
  }
  await expectThemedScreenshot(component, 'form-sections');

  const before = await page.evaluate(() => window.scrollY);
  await component.getByText('Third group', { exact: true }).first().click();
  await expect
    .poll(() => page.evaluate(() => window.scrollY))
    .toBeGreaterThan(before);
});
