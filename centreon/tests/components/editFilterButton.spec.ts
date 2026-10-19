import {
  labelDelete,
  labelManageFilters
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Resources/Filter/Edit/EditButton/EditFilterButton.
const storyId = 'EditButton/EditFilterButton/Default';
const filtersEndpoint = './api/latest/users/filters/events-view';

const multiSelect = (
  name: string,
  value: Array<unknown> = [],
  objectType: string | null = name
) => ({ name, object_type: objectType, type: 'multi_select', value });

const filter = {
  criterias: [
    multiSelect('resource_types', [{ id: 'host', name: 'Host' }], null),
    multiSelect(
      'states',
      [{ id: 'unhandled_problems', name: 'Unhandled' }],
      null
    ),
    multiSelect('statuses', [{ id: 'OK', name: 'Ok' }], null),
    multiSelect('host_groups', [{ id: 0, name: 'Linux-servers' }]),
    multiSelect('service_groups', [{ id: 0, name: 'Web-services' }]),
    multiSelect('monitoring_servers'),
    multiSelect('host_categories', [{ id: 0, name: 'Linux' }]),
    multiSelect('service_categories', [{ id: 0, name: 'web-services' }]),
    { name: 'search', object_type: null, type: 'text', value: 'my search' },
    {
      name: 'sort',
      object_type: null,
      type: 'array',
      value: ['status_severity_code', 'desc']
    }
  ],
  id: 0,
  name: 'MyFilter'
};

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: filtersEndpoint,
    response: { meta: { limit: 30, page: 1, total: 1 }, result: [filter] }
  });
});

const openEdition = async (
  component: import('@playwright/test').Locator
): Promise<void> => {
  await component.getByLabel(labelManageFilters).click();
  await expect(component.getByRole('textbox').first()).toHaveValue('MyFilter');
};

test('lists the saved filters in the edition panel', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await openEdition(component);

  await expectThemedScreenshot(component, 'edit-filters-panel');
});

test('updates a renamed filter', async ({ mockApi, mount }) => {
  const update = await mockApi({
    endpoint: `${filtersEndpoint}/0`,
    method: 'PUT'
  });
  const component = await mount(storyId);
  await openEdition(component);

  await component.getByRole('textbox').first().fill('MyFilterupdated');
  await component.getByText(labelManageFilters).first().click();

  await expect.poll(() => update.bodies.length).toBeGreaterThan(0);
  expect(update.bodies.at(-1)).toMatchObject({ name: 'MyFilterupdated' });
});

test('deletes a filter after confirmation', async ({
  mockApi,
  mount,
  page
}) => {
  const deletion = await mockApi({
    endpoint: `${filtersEndpoint}/0`,
    method: 'DELETE',
    status: 204
  });
  const component = await mount(storyId);
  await openEdition(component);

  await component.getByLabel(labelDelete).click();
  await page.getByRole('dialog').getByLabel(labelDelete).click();

  await expect.poll(() => deletion.urls.length).toBe(1);
});
