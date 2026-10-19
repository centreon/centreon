import { expect, type MockApi, test } from '../fixtures';

/**
 * packages/ui CrudPage: the welcome page, the listing and its search, the
 * add and update forms, and the deletion of items and sub items.
 */
const story = 'ui/components/CrudPage/CrudPage/Default';

const items = Array.from({ length: 30 }, (_, id) => ({
  description: `Description ${id}`,
  id,
  name: `Item ${id}`,
  subItems: [{ id: 1, name: 'SubItem' }]
}));

const mockItems = async (mockApi: MockApi) => {
  const listing = await mockApi({
    endpoint: 'listing',
    response: { meta: { limit: 30, page: 1, total: 60 }, result: items }
  });
  // Registered last: it wins over `listing` for the empty listing URLs.
  await mockApi({
    endpoint: 'empty-listing',
    response: { meta: { limit: 30, page: 1, total: 0 }, result: [] }
  });
  const deleteItem = await mockApi({
    endpoint: 'listing/1',
    method: 'DELETE',
    response: {}
  });
  const deleteSubItem = await mockApi({
    endpoint: 'listing/0/subItems/1',
    method: 'DELETE',
    response: {}
  });
  await mockApi({ endpoint: 'item/0', response: items[0] });

  return { deleteItem, deleteSubItem, listing };
};

test('welcomes on an empty page, and opens the add form from there', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  await mockItems(mockApi);
  const component = await mount(story, { empty: true });
  await expect(component.getByText('Welcome to the items page')).toBeVisible();
  await expect(component.getByText('This page handles item')).toBeVisible();
  await expect(component.getByText('Items', { exact: true })).toBeVisible();
  await expectThemedScreenshot(component, 'crud-page-welcome');

  await component.locator('button', { hasText: 'Create item' }).first().click();
  await expect(page.getByText('Add item')).toBeVisible();
  await expect(
    page.getByText('This is a placeholder for the form')
  ).toBeVisible();
});

test('lists the items, and searches them with the search and filters', async ({
  expectThemedScreenshot,
  mockApi,
  mount,
  page
}) => {
  const { listing } = await mockItems(mockApi);
  const component = await mount(story);
  await expect(component.getByText('Item 0', { exact: true })).toBeVisible();
  await expect(
    component.getByText('Description 0', { exact: true })
  ).toBeVisible();
  const [first] = listing.urls;
  expect(
    Object.fromEntries(
      ['page', 'limit', 'sort_by', 'search'].map((key) => [
        key,
        first.searchParams.get(key)
      ])
    )
  ).toEqual({
    limit: '10',
    page: '1',
    search:
      '{"$and":[{"$or":[{"name":{"$rg":""}}]},{"$and":[{"$or":[{"hasDescription":{"$in":true}}]},{"$or":[{"isEven":{"$in":false}}]}]}]}',
    sort_by: '{"name":"asc"}'
  });
  await expectThemedScreenshot(component, 'crud-page-listing');

  await component.getByTestId('Search').nth(1).fill('simple search');
  await component.getByLabel('filters').click();
  await page.getByLabel('Has description').click();
  await page.getByLabel('Is even').click();
  await component.getByLabel('filters').click();
  await expect(page.getByLabel('Has description')).toHaveCount(0);
  await expect
    .poll(() => listing.urls.at(-1)?.searchParams.get('search'))
    .toBe(
      '{"$and":[{"$or":[{"name":{"$rg":"simple search"}}]},{"$and":[{"$or":[{"hasDescription":{"$in":false}}]},{"$or":[{"isEven":{"$in":true}}]}]}]}'
    );

  await component.getByRole('button', { name: 'Create item' }).click();
  await expect(page.getByText('Add item')).toBeVisible();
});

test('opens the update form of an item, and closes it', async ({
  mockApi,
  mount,
  page
}) => {
  await mockItems(mockApi);
  const component = await mount(story);
  await component.getByTestId('edit-0').click();
  await expect(page.getByText('Update item')).toBeVisible();
  await expect(
    page.getByText(
      'Initial values: {"description":"Description 0","id":0,"name":"Item 0","subItems":[{"id":1,"name":"SubItem"}]}'
    )
  ).toBeVisible();
  await page.getByLabel('close').click();
  await expect(page.getByText('Update item')).toHaveCount(0);
});

for (const basicLabels of [false, true]) {
  test(`deletes items and sub items with ${basicLabels ? 'basic' : 'item'} labels`, async ({
    mockApi,
    mount,
    page
  }) => {
    const { deleteItem, deleteSubItem } = await mockItems(mockApi);
    const confirm = () =>
      page.getByRole('button', { exact: true, name: 'Delete' }).click();

    const component = await mount(story, { basicLabels });
    await component.getByTestId('delete-1').click();
    await expect(
      page.getByText(basicLabels ? 'Title' : 'Delete item', { exact: true })
    ).toBeVisible();
    await expect(
      page.getByText(
        basicLabels ? 'A small description' : 'The item Item 1 will be deleted'
      )
    ).toBeVisible();
    await confirm();
    await expect.poll(() => deleteItem.urls.length).toBe(1);
    await expect(
      page.getByText(basicLabels ? 'This is a success' : 'Item deleted')
    ).toBeVisible();

    const withSubItems = await mount(story, {
      basicLabels,
      subItems: 'deletable'
    });
    await withSubItems.getByTestId('Expand 0').click();
    await withSubItems.getByTestId('delete-0-1').click();
    await expect(
      page.getByText(basicLabels ? 'Title' : 'Delete sub item', { exact: true })
    ).toBeVisible();
    await expect(
      page.getByText(
        basicLabels
          ? 'A small description'
          : 'The sub item SubItem from the item Item 0 will be deleted'
      )
    ).toBeVisible();
    await confirm();
    await expect.poll(() => deleteSubItem.urls.length).toBe(1);
    await expect(
      page.getByText(basicLabels ? 'This is a success' : 'Sub item deleted')
    ).toBeVisible();
  });
}

test('offers no deletion of the sub items when they are not deletable', async ({
  mockApi,
  mount
}) => {
  await mockItems(mockApi);
  const component = await mount(story, { subItems: 'undeletable' });
  await component.getByTestId('Expand 0').click();
  await expect(component.getByText('SubItem')).toBeVisible();
  await expect(component.getByTestId('delete-0-1')).toHaveCount(0);
});
