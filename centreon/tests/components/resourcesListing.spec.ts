import type { Page } from '@playwright/test';

import listingWithStates from '../../cypress/fixtures/resources/listing/listingWithStates.json';
import {
  labelAcknowledged,
  labelAll,
  labelInDowntime,
  labelResourceFlapping,
  labelViewByHost,
  labelViewByService
} from '../../www/front_src/src/Resources/translatedLabels';
import {
  expect,
  type MockApi,
  type MockedEndpoint,
  moveSortable,
  test
} from './fixtures';

// Migrated from Resources/Listing/ResourceListing.
const storyId = 'Resources/Listing/Listing/Default';

type Mount = (id: string, props?: object) => Promise<unknown>;

interface ColumnData {
  id: string;
  label: string;
  shortLabel?: string;
  sortable?: boolean;
}

interface Entity {
  information: string;
  is_acknowledged: boolean;
  is_in_downtime: boolean;
  name: string;
}

interface ListingData {
  columns: Array<ColumnData>;
  columnToSort: Array<ColumnData>;
  defaultSelectedColumnIds: Array<string>;
  entities: Array<Entity>;
  retrievedListing: { result: Array<Entity> };
  retrievedListingByHosts: unknown;
  retrievedListingWithCriticalResources: unknown;
}

interface MountOptions {
  columnPreset?: 'states' | 'viewByHost';
  listing?: unknown;
  listingVariant?: 'compact' | 'extended';
  notification?: boolean;
  visualization?: 'All' | 'By host' | 'By service';
}

let data: ListingData;
let listing: MockedEndpoint;
let listingByHosts: MockedEndpoint;

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ loadStoryData }) => {
  data = await loadStoryData<ListingData>(
    'Resources/Listing/Listing/Data',
    'resourceListingData'
  );
});

const mountListing = async (
  { mockApi, mount }: { mockApi: MockApi; mount: Mount },
  { listing: response = data.retrievedListing, ...props }: MountOptions = {}
): Promise<void> => {
  await mockApi({
    endpoint: 'events-view',
    response: { meta: { limit: 10, page: 1, total: 0 }, result: [] }
  });
  listing = await mockApi({
    endpoint: './api/latest/monitoring/resources',
    response
  });
  listingByHosts = await mockApi({
    endpoint: './api/latest/monitoring/resources/hosts',
    response: data.retrievedListingByHosts
  });
  await mount(storyId, props);
};

const lastParam = (mock: MockedEndpoint, name: string): unknown => {
  const value = new URL(mock.urls.at(-1) ?? '').searchParams.get(name);

  return value === null ? null : JSON.parse(value);
};

const addColumns = (page: Page) => page.getByLabel('Add columns');

test.describe('Resource Listing', () => {
  test('displays the first line of multiline information', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount });
    for (const { information } of data.retrievedListing.result) {
      await expect(
        page.getByText(information.split('\n')[0]).first()
      ).toBeAttached();
    }
    await expectThemedScreenshot(page, 'resources-listing-information');
  });

  for (const listingVariant of ['compact', 'extended'] as const) {
    test(`displays the listing in ${listingVariant} mode`, async ({
      expectThemedScreenshot,
      mockApi,
      mount,
      page
    }) => {
      await mountListing({ mockApi, mount }, { listingVariant });
      await expect(page.getByText('E0', { exact: true })).toBeVisible();
      await expectThemedScreenshot(page, `resources-listing-${listingVariant}`);
    });
  }

  test('highlights the rows of resources in a critical state', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await mountListing(
      { mockApi, mount },
      { listing: data.retrievedListingWithCriticalResources }
    );
    await expect(page.getByText('E0', { exact: true })).toBeVisible();
    await expectThemedScreenshot(page, 'resources-listing-critical');
  });

  test('reorders columns when a drag handle is focused and an arrow is pressed', async ({
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount });
    const headers = () =>
      page
        .locator('[aria-label^="Column "]')
        .evaluateAll((elements) =>
          elements.map((element) => element.getAttribute('aria-label'))
        );
    const before = await headers();
    expect(before).toContain('Column Parent');
    await moveSortable(
      page,
      page.getByLabel('Parent Drag handle'),
      'ArrowRight'
    );
    await expect.poll(headers).not.toEqual(before);
  });
});

test.describe('Resource Listing: visualization by service', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await mountListing({ mockApi, mount });
    await expect(page.getByTestId('tree view')).toBeVisible();
    await page.getByLabel(labelViewByService).click();
  });

  test('requests services and meta services sorted by worst status and duration', async () => {
    await expect
      .poll(() => lastParam(listing, 'types'))
      .toEqual(['service', 'metaservice']);
    expect(lastParam(listing, 'sort_by')).toEqual({
      last_status_change: 'desc',
      status_severity_code: 'desc'
    });
  });

  test('disables the columns drag and drop and renames the columns', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect
      .poll(() => lastParam(listing, 'types'))
      .toEqual(['service', 'metaservice']);
    await expect(page.getByLabel(/Drag handle$/)).toHaveCount(0);
    await expect(page.getByText('Resource', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Parent', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Service', { exact: true })).toBeVisible();
    await expect(page.getByText('Host', { exact: true })).toBeVisible();
    await expectThemedScreenshot(page, 'resources-listing-by-service');
  });
});

test.describe('Resource Listing: visualization by host', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await mountListing(
      { mockApi, mount },
      { columnPreset: 'viewByHost', visualization: 'By host' }
    );
    await page.getByLabel(labelViewByHost).click();
  });

  test('requests the hosts sorted by worst status and duration', async () => {
    await expect
      .poll(() => lastParam(listingByHosts, 'types'))
      .toEqual(['host']);
    expect(lastParam(listingByHosts, 'sort_by')).toEqual({
      last_status_change: 'desc',
      status_severity_code: 'desc'
    });
  });

  test('disables the columns drag and drop and renames the columns', async ({
    expectThemedScreenshot,
    page
  }) => {
    await expect(page.getByText('Services', { exact: true })).toBeVisible();
    await expect(page.getByLabel(/Drag handle$/)).toHaveCount(0);
    await expect(page.getByText('Resource', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Parent', { exact: true })).toHaveCount(0);
    await expect(page.getByText('State', { exact: true })).toBeVisible();
    await expect(page.getByText('Host', { exact: true })).toBeVisible();
    await expectThemedScreenshot(page, 'resources-listing-by-host');
  });

  test('displays the services when the Expand button is clicked', async ({
    expectThemedScreenshot,
    page
  }) => {
    await page.getByLabel('Expand 14').click();
    for (const service of ['Disk-/', 'Load', 'Memory', 'Ping']) {
      await expect(page.getByText(service, { exact: true })).toBeVisible();
    }
    await expectThemedScreenshot(page, 'resources-listing-by-host-expanded');
  });
});

test.describe('Resource Listing: visualization of all resources', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await mountListing({ mockApi, mount }, { visualization: 'By service' });
    await page.getByRole('button', { exact: true, name: labelAll }).click();
  });

  test('requests all resources sorted by newest duration', async () => {
    await expect
      .poll(() => lastParam(listing, 'sort_by'))
      .toEqual({ last_status_change: 'desc' });
    expect(lastParam(listing, 'types')).toBeNull();
  });

  test('restores the default column names and the drag and drop', async ({
    page
  }) => {
    await expect(page.getByText('Resource', { exact: true })).toBeVisible();
    await expect(page.getByText('Parent', { exact: true })).toBeVisible();
    await expect(page.getByText('Service', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Host', { exact: true })).toHaveCount(0);
    for (const { label } of data.columnToSort) {
      await expect(page.getByLabel(`${label} Drag handle`)).toBeAttached();
    }
  });
});

test('sorts the listing when a column header is clicked', async ({
  mockApi,
  mount,
  page
}) => {
  await mountListing({ mockApi, mount });
  await expect(page.getByText('E0', { exact: true })).toBeVisible();
  for (const { label } of data.columnToSort) {
    await test.step(label, async () => {
      const sortBy = () => lastParam(listing, 'sort_by');
      const initial = sortBy();
      await page.getByLabel(`Column ${label}`).click();
      await expect.poll(sortBy).not.toEqual(initial);
      const first = sortBy();
      await page.getByLabel(`Column ${label}`).click();
      await expect.poll(sortBy).not.toEqual(first);
    });
  }
});

test.describe('Listing request', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await mountListing({ mockApi, mount });
    await expect(page.getByText('E0', { exact: true })).toBeVisible();
  });

  test('requests the page chosen with the pagination actions', async ({
    page
  }) => {
    for (const action of [
      'Next page',
      'Previous page',
      'Last page',
      'First page'
    ]) {
      const calls = listing.urls.length;
      await page.getByLabel(action).click();
      await expect.poll(() => listing.urls.length).toBe(calls + 1);
    }
    const pages = listing.urls.map((url) =>
      new URL(url).searchParams.get('page')
    );
    expect(pages).toEqual(['1', '2', '1', '4', '1']);
    expect(lastParam(listing, 'limit')).toBe(30);
  });

  test('requests the number of rows chosen per page', async ({ page }) => {
    await page.locator('[id="Rows per page"]').click();
    await page.getByRole('option', { exact: true, name: '30' }).click();
    await expect.poll(() => lastParam(listing, 'limit')).toBe(30);
  });
});

test.describe('Display additional columns', () => {
  test.beforeEach(async ({ mockApi }) => {
    const details = {
      author_name: 'admin',
      comment: 'Set by admin',
      id: 0
    };
    await mockApi({
      endpoint: 'monitoring/downtime/0',
      response: {
        result: [
          {
            ...details,
            end_time: '2020-02-28T08:18:16Z',
            is_fixed: true,
            start_time: '2020-02-28T08:16:16Z'
          }
        ]
      }
    });
    await mockApi({
      endpoint: 'monitoring/acknowledgement/0',
      response: {
        result: [
          {
            ...details,
            entry_time: '2020-02-28T08:16:00Z',
            is_persistent_comment: true,
            is_sticky: false
          }
        ]
      }
    });
  });

  const hoverStateChip = async (page: Page, label: string): Promise<void> => {
    await addColumns(page).click();
    await page.getByText('State', { exact: true }).click();
    await addColumns(page).click();
    await page.getByLabel(label).dispatchEvent('mouseover');
  };

  test('displays downtime details when the downtime state chip is hovered', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount });
    const entity = data.entities.find(({ is_in_downtime }) => is_in_downtime);
    await hoverStateChip(page, `${entity?.name} ${labelInDowntime}`);
    for (const value of [
      'admin',
      'Yes',
      '02/28/2020 9:16 AM',
      '02/28/2020 9:18 AM',
      'Set by admin'
    ]) {
      await expect(page.getByText(value, { exact: true })).toBeVisible();
    }
    await expectThemedScreenshot(page, 'resources-listing-downtime-details');
  });

  test('displays acknowledgement details when an acknowledged state chip is hovered', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount });
    const entity = data.entities.find(({ is_acknowledged }) => is_acknowledged);
    await hoverStateChip(page, `${entity?.name} ${labelAcknowledged}`);
    for (const value of [
      'admin',
      'Yes',
      '02/28/2020 9:16 AM',
      'No',
      'Set by admin'
    ]) {
      await expect(page.getByText(value, { exact: true })).toBeVisible();
    }
    await expectThemedScreenshot(
      page,
      'resources-listing-acknowledgement-details'
    );
  });

  test('displays the additional columns selected from the menu', async ({
    mockApi,
    mount,
    page
  }) => {
    const additional = data.columns.filter(
      ({ id }) => ![...data.defaultSelectedColumnIds, 'state'].includes(id)
    );
    expect(additional.length).toBeGreaterThan(0);
    for (const { id, label, shortLabel } of additional) {
      await test.step(id, async () => {
        await mountListing({ mockApi, mount });
        await addColumns(page).click();
        const displayLabel = shortLabel ? `${label} (${shortLabel})` : label;
        const labels = page.getByText(displayLabel, { exact: true });
        await labels.last().click();
        // The header shows the short label when there is one.
        await expect(labels).toHaveCount(shortLabel ? 1 : 2);
        await addColumns(page).click();
      });
    }
  });
});

test.describe('Notification column', () => {
  test('is listed when the cloud notification feature is disabled', async ({
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount });
    await page.getByTestId('Add columns').click();
    await expect(page.getByText('Notification (Notif)')).toBeAttached();
  });

  test('is hidden when the cloud notification feature is enabled', async ({
    mockApi,
    mount,
    page
  }) => {
    await mountListing({ mockApi, mount }, { notification: true });
    await page.getByTestId('Add columns').click();
    await expect(page.getByText('Severity (S)')).toBeAttached();
    await expect(page.getByText('Notification (Notif)')).toHaveCount(0);
  });
});

test.describe('Resource Listing: row and icon colors', () => {
  test.beforeEach(async ({ mockApi, mount, page }) => {
    await mountListing(
      { mockApi, mount },
      { columnPreset: 'states', listing: listingWithStates }
    );
    await expect(page.getByText('Memory').first()).toBeVisible();
  });

  test('displays state icons when resources are in one or more states', async ({
    page
  }) => {
    const table = page.getByRole('table');
    await expect(table.getByTestId('DowntimeIcon')).toHaveCount(2);
    await expect(table.getByTestId('PersonIcon')).toHaveCount(2);
    await expect(page.getByTestId('FlappingIcon')).toHaveCount(4);
    await page.getByTestId('FlappingIcon').first().dispatchEvent('mouseover');
    await expect(page.getByText(labelResourceFlapping)).toBeVisible();
  });

  for (const { color, row, state } of [
    { color: 'rgb(229, 216, 243)', row: 1, state: 'downtime' },
    { color: 'rgb(223, 210, 185)', row: 2, state: 'acknowledged' },
    { color: 'rgb(216, 243, 239)', row: 4, state: 'flapping' }
  ]) {
    test(`displays the row in the ${state} color`, async ({ page }) => {
      const rows = page.getByRole('row');
      await expect(rows).toHaveCount(7);
      await expect(rows.nth(row).locator('> *').first()).toHaveCSS(
        'background-color',
        color
      );
    });
  }

  test('displays a Down icon when the parent status is null', async ({
    expectThemedScreenshot,
    page
  }) => {
    await page.getByTestId('Add columns').click();
    await page.getByText('Parent', { exact: true }).click();
    await expect(page.getByText(/^D$/).first()).toBeVisible();
    await expectThemedScreenshot(page, 'resources-listing-parent-down');
  });
});
