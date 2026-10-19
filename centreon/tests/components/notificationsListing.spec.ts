import type { Locator, Page } from '@playwright/test';

import {
  labelCancel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelDiscard,
  labelDuplicate,
  labelFailedToDeleteNotifications,
  labelFailedToDeleteSelectedNotifications,
  labelNotificationDuplicated,
  labelNotificationName,
  labelNotificationSuccessfullyDeleted,
  labelNotificationsSuccessfullyDeleted,
  labelPleaseEnterNameForDuplicatedNotification,
  labelRequired,
  labelThisNameAlreadyExists
} from '../../www/front_src/src/CloudNotificationsConfiguration/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from CloudNotificationsConfiguration/Listing/Tests/NotificationsListing.
const storyId = 'Listing/NotificationsListing/Default';
const notificationsEndpoint = './api/latest/configuration/notifications';

interface ListingData {
  bulkDeletion: Record<'failed' | 'success' | 'warning', unknown>;
  listing: Record<'limit20' | 'page1' | 'page2' | 'page6', unknown>;
  notification: unknown;
  sortableColumns: Array<{ label: string; sortBy: string }>;
}

let data: ListingData;
let listing: { urls: Array<URL> };

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ loadStoryData, mockApi, page }) => {
  // The listing fixtures are random: seed Math.random for stable screenshots.
  await page.addInitScript(() => {
    let seed = 42;
    Math.random = () => {
      seed = (seed * 16807) % 2147483647;

      return (seed - 1) / 2147483646;
    };
  });
  data = await loadStoryData<ListingData>(
    'Listing/NotificationsListing/Data',
    'notificationsListingData'
  );
  // One listing per page or limit, like the Cypress intercepts.
  listing = await mockApi({
    endpoint: notificationsEndpoint,
    response: (url: URL) => {
      if (url.searchParams.get('limit') === '20') {
        return data.listing.limit20;
      }
      const page = url.searchParams.get('page');

      return page === '2' || page === '6'
        ? data.listing[`page${page}`]
        : data.listing.page1;
    }
  });
});

const lastQuery = (key: string) => () =>
  listing.urls.at(-1)?.searchParams.get(key);
const loaded = async (component: Locator) => {
  await expect(
    component.getByText('notification1', { exact: true })
  ).toBeVisible();
};
const selectRows = async (component: Locator) => {
  for (const row of [1, 2, 3]) {
    await component.getByLabel(`Select row ${row}`, { exact: true }).click();
  }
};
const clickOutside = (page: Page) => page.mouse.click(1, 1);

test.describe('listing', () => {
  test('displays the first page', async ({ expectThemedScreenshot, mount }) => {
    const component = await mount(storyId);

    await loaded(component);
    await expectThemedScreenshot(component, 'notifications-listing');
  });

  test('changes the page size', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.locator('[id="Rows per page"]').click();
    await page.getByRole('option', { exact: true, name: '20' }).click();
    await expect.poll(lastQuery('limit')).toBe('20');

    await component.locator('[id="Rows per page"]').click();
    await page.getByRole('option', { exact: true, name: '10' }).click();
    await expect.poll(lastQuery('limit')).toBe('10');
    await loaded(component);
  });

  test('changes the page', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    for (const [button, expected] of [
      ['Next page', '2'],
      ['Previous page', '1'],
      ['Last page', '6'],
      ['First page', '1']
    ] as const) {
      await component.getByLabel(button).click();
      await expect.poll(lastQuery('page')).toBe(expected);
    }
    await loaded(component);
  });

  test('sorts by each sortable column', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    for (const { label, sortBy } of data.sortableColumns) {
      for (const order of ['desc', 'asc']) {
        await component.getByLabel(`Column ${label}`).click();
        await expect
          .poll(() => JSON.parse(lastQuery('sort_by')() ?? '{}'))
          .toEqual({ [sortBy]: order });
      }
    }
  });
});

test.describe('bulk deletion', () => {
  const deleteSelection = (component: Locator) =>
    component.getByTestId('delete multiple notifications');

  test('enables the delete action with a selection', async ({ mount }) => {
    const component = await mount(storyId);
    await loaded(component);

    await expect(deleteSelection(component)).toBeDisabled();
    await selectRows(component);
    await expect(deleteSelection(component)).toBeEnabled();
  });

  const outcomes = [
    {
      message: labelNotificationsSuccessfullyDeleted,
      name: 'confirms a successful deletion',
      response: 'success' as const
    },
    {
      message: `${labelFailedToDeleteNotifications}: notification1, notification2`,
      name: 'names the notifications that could not be deleted',
      response: 'warning' as const
    },
    {
      message: labelFailedToDeleteSelectedNotifications,
      name: 'reports a failed deletion',
      response: 'failed' as const
    }
  ];

  for (const { message, name, response } of outcomes) {
    test(name, async ({ mockApi, mount, page }) => {
      const deletion = await mockApi({
        endpoint: `${notificationsEndpoint}/_delete`,
        method: 'POST',
        response: data.bulkDeletion[response]
      });
      const component = await mount(storyId);
      await loaded(component);

      await selectRows(component);
      await deleteSelection(component).click();
      const dialog = page.getByRole('dialog');
      await expect(dialog.getByText(labelDeleteNotification)).toBeVisible();
      await expect(
        dialog.getByText(labelDeleteNotificationWarning)
      ).toBeVisible();
      await dialog.getByText(labelDelete, { exact: true }).click();

      await expect.poll(() => deletion.urls.length).toBe(1);
      await expect(page.getByText(message)).toBeVisible();
    });
  }

  test('cancels the deletion', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);

    await selectRows(component);
    await deleteSelection(component).click();
    await page.getByRole('dialog').getByText(labelCancel).click();

    await expect(page.getByRole('dialog')).toHaveCount(0);
  });
});

test.describe('row deletion', () => {
  test('asks for a confirmation naming the notification', async ({
    mount,
    page
  }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.getByTestId(labelDeleteNotification).first().click();

    const dialog = page.getByRole('dialog');
    await expect(
      dialog.getByText(`${labelDelete} « notification1 ».`)
    ).toBeVisible();
    await expect(
      dialog.getByText(labelDeleteNotificationWarning)
    ).toBeVisible();
    await dialog.getByText(labelCancel).click();
  });

  const outcomes = [
    {
      message: labelNotificationSuccessfullyDeleted,
      response: { data: [{ status: 204 }] },
      status: 200
    },
    {
      message: 'internal server error',
      response: { code: 'ok', message: 'internal server error' },
      status: 500
    }
  ];

  for (const { message, response, status } of outcomes) {
    test(`reports a ${status === 200 ? 'successful' : 'failed'} deletion`, async ({
      mockApi,
      mount,
      page
    }) => {
      const deletion = await mockApi({
        endpoint: `${notificationsEndpoint}/1`,
        method: 'DELETE',
        response,
        status
      });
      const component = await mount(storyId);
      await loaded(component);

      await component.getByTestId(labelDeleteNotification).first().click();
      await page.getByRole('dialog').getByLabel(labelDelete).click();

      await expect.poll(() => deletion.urls.length).toBe(1);
      await expect(page.getByText(message)).toBeVisible();
    });
  }
});

test.describe('row duplication', () => {
  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: `${notificationsEndpoint}/1`,
      response: data.notification
    });
  });

  test('validates the name of the copy', async ({ mount, page }) => {
    const component = await mount(storyId);
    await loaded(component);

    await component.getByTestId(labelDuplicate).first().click();
    const dialog = page.getByRole('dialog');
    const name = dialog.getByLabel(labelNotificationName);
    const confirm = dialog.getByTestId('Confirm');
    await expect(
      dialog.getByText(labelPleaseEnterNameForDuplicatedNotification)
    ).toBeVisible();
    await expect(name).toHaveAttribute('required', '');
    await expect(confirm).toBeDisabled();

    await name.fill('notification1');
    await clickOutside(page);
    await expect(dialog.getByText(labelThisNameAlreadyExists)).toBeVisible();
    await expect(confirm).toBeDisabled();

    await name.clear();
    await clickOutside(page);
    await expect(dialog.getByText(labelRequired)).toBeVisible();
    await expect(confirm).toBeDisabled();

    await dialog.getByText(labelDiscard).click();
    await expect(page.getByText(labelDiscard)).toHaveCount(0);
  });

  const outcomes = [
    {
      message: labelNotificationDuplicated,
      response: { status: 'ok' },
      status: 200
    },
    {
      message: 'internal server error',
      response: { code: '500', message: 'internal server error' },
      status: 500
    }
  ];

  for (const { message, response, status } of outcomes) {
    test(`reports a ${status === 200 ? 'successful' : 'failed'} duplication`, async ({
      mockApi,
      mount,
      page
    }) => {
      const duplication = await mockApi({
        endpoint: notificationsEndpoint,
        method: 'POST',
        response,
        status
      });
      const component = await mount(storyId);
      await loaded(component);

      await component.getByTestId(labelDuplicate).first().click();
      const dialog = page.getByRole('dialog');
      await dialog.getByLabel(labelNotificationName).fill('New name');
      await dialog.getByTestId('Confirm').click();

      await expect.poll(() => duplication.urls.length).toBe(1);
      await expect(page.getByText(message)).toBeVisible();
    });
  }
});
