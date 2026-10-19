import { expect, test } from './fixtures';

// Migrated from CloudNotificationsConfiguration/Filter/NotificationsFilter and
// ResourceAccessManagement/Filter/ResourceAccessRulesFilter Cypress specs.

const emptyListing = { meta: { limit: 10, page: 1, total: 0 }, result: [] };

const filters = [
  {
    endpoint: './api/latest/configuration/notifications',
    name: 'Notifications',
    searchedFields: ['name'],
    storyId: 'NotificationsFilter/Default'
  },
  {
    endpoint: './api/latest/administration/resource-access/rules',
    name: 'Resource access rules',
    searchedFields: ['name', 'description'],
    storyId: 'ResourceAccessRulesFilter/Default'
  }
];

for (const { endpoint, name, searchedFields, storyId } of filters) {
  test(`${name} filter sends the typed search to the listing`, async ({
    mockApi,
    mount
  }) => {
    const listing = await mockApi({ endpoint, response: emptyListing });

    const component = await mount(storyId);
    await expect.poll(() => listing.urls.length).toBeGreaterThan(0);

    await component.getByPlaceholder('Search').fill('foobar');

    // The search is debounced: poll until a request carries it.
    await expect
      .poll(() =>
        listing.urls
          .map((url) => url.searchParams.get('search'))
          .filter(Boolean)
          .map((search) => JSON.parse(search as string))
      )
      .toContainEqual({
        $or: searchedFields.map((field) => ({ [field]: { $rg: 'foobar' } }))
      });
  });
}
