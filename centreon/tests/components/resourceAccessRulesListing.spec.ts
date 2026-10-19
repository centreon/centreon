import type { Locator, Page } from '@playwright/test';

import { findResourceAccessRuleResponse } from '../../www/front_src/src/ResourceAccessManagement/AddEditResourceAccessRule/specs/testUtils';
import {
  labelActiveOrInactive,
  labelCancel,
  labelDelete,
  labelDeleteMultipleResourceAccessRules,
  labelDeleteResourceAccessRule,
  labelDeleteResourceAccessRuleDialogMessage,
  labelDeleteResourceAccessRules,
  labelDeleteResourceAccessRulesDialogMessage,
  labelDeleteResourceAccessRulesWarning,
  labelDeleteResourceAccessRuleWarning,
  labelDuplicate,
  labelEnterNameForDuplicatedRule,
  labelFailedToDeleteSelectedRules,
  labelNameAlreadyExists,
  labelRequired,
  labelResourceAccessRuleDeletedSuccess,
  labelResourceAccessRuleName,
  labelResourceAccessRulesDeletedSuccess,
  labelRuleDuplicatedSuccess
} from '../../www/front_src/src/ResourceAccessManagement/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from ResourceAccessManagement/Listing/Tests/ResourceAccessRulesListing.
const storyId = 'Listing/ResourceAccessRulesListing/Default';
const rulesEndpoint = './api/latest/administration/resource-access/rules';

interface ListingData {
  bulkDeletion: Record<'failed' | 'success' | 'warning', unknown>;
  listing: Record<'limit20' | 'page1' | 'page2' | 'page7', unknown>;
  sortableColumns: Array<{ label: string; sortBy: string }>;
}

let data: ListingData;
let listing: { urls: Array<URL> };

test.use({ viewport: { height: 800, width: 1280 } });

test.beforeEach(async ({ loadStoryData, mockApi }) => {
  data = await loadStoryData<ListingData>(
    'Listing/ResourceAccessRulesListing/Data',
    'resourceAccessRulesListingData'
  );
  listing = await mockApi({
    endpoint: rulesEndpoint,
    response: (url: URL) => {
      if (url.searchParams.get('limit') === '20') {
        return data.listing.limit20;
      }
      const page = url.searchParams.get('page');

      return page === '2' || page === '7'
        ? data.listing[`page${page}`]
        : data.listing.page1;
    }
  });
});

const lastQuery = (key: string) => () =>
  listing.urls.at(-1)?.searchParams.get(key);
const mountListing = async (mount: (id: string) => Promise<Locator>) => {
  const component = await mount(storyId);
  await expect(component.getByText('rule1', { exact: true })).toBeVisible();

  return component;
};
const selectRows = async (component: Locator) => {
  for (const row of [1, 2, 3]) {
    await component.getByLabel(`Select row ${row}`, { exact: true }).click();
  }
};

test.describe('listing', () => {
  test('displays the first page', async ({ expectThemedScreenshot, mount }) => {
    const component = await mountListing(mount);

    await expectThemedScreenshot(component, 'resource-access-rules-listing');
  });

  test('changes the page size', async ({ mount, page }) => {
    const component = await mountListing(mount);

    for (const limit of ['20', '10']) {
      await component.locator('[id="Rows per page"]').click();
      await page.getByRole('option', { exact: true, name: limit }).click();
      await expect.poll(lastQuery('limit')).toBe(limit);
    }
  });

  test('changes the page', async ({ mount }) => {
    const component = await mountListing(mount);

    for (const [button, expected] of [
      ['Next page', '2'],
      ['Previous page', '1'],
      ['Last page', '7'],
      ['First page', '1']
    ] as const) {
      await component.getByLabel(button).click();
      await expect.poll(lastQuery('page')).toBe(expected);
    }
  });

  test('sorts by each sortable column', async ({ mount }) => {
    const component = await mountListing(mount);

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

test.describe('row deletion', () => {
  const openDeletion = async (component: Locator, page: Page) => {
    await component.getByTestId(labelDeleteResourceAccessRule).first().click();

    return page.getByRole('dialog');
  };

  test('asks for a confirmation naming the rule', async ({ mount, page }) => {
    const component = await mountListing(mount);
    const dialog = await openDeletion(component, page);

    await expect(dialog.getByText(labelDeleteResourceAccessRule)).toBeVisible();
    await expect(
      dialog.getByText(`rule0 ${labelDeleteResourceAccessRuleDialogMessage}`)
    ).toBeVisible();
    await expect(
      dialog.getByText(labelDeleteResourceAccessRuleWarning)
    ).toBeVisible();
    await expect(dialog.getByLabel(labelDelete)).toBeVisible();

    await dialog.getByTestId(labelCancel).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
  });

  const outcomes = [
    {
      message: labelResourceAccessRuleDeletedSuccess,
      response: { data: [{ status: 204 }] },
      status: 200
    },
    {
      message: 'internal server error',
      response: { message: 'internal server error' },
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
        endpoint: `${rulesEndpoint}/1`,
        method: 'DELETE',
        response,
        status
      });
      const component = await mountListing(mount);
      const dialog = await openDeletion(component, page);
      await dialog.getByLabel(labelDelete).click();

      await expect.poll(() => deletion.urls.length).toBe(1);
      await expect(page.getByText(message)).toBeVisible();
    });
  }
});

test('reports a failed activation toggle', async ({ mockApi, mount, page }) => {
  const activation = await mockApi({
    endpoint: `${rulesEndpoint}/1`,
    method: 'PATCH',
    response: { message: 'internal server error' },
    status: 500
  });
  const component = await mountListing(mount);
  await component.getByLabel(labelActiveOrInactive).first().click();

  await expect.poll(() => activation.urls.length).toBe(1);
  await expect(page.getByText('internal server error')).toBeVisible();
});

test.describe('bulk deletion', () => {
  const deleteSelection = (component: Locator) =>
    component.getByTestId(labelDeleteMultipleResourceAccessRules);

  test('enables the delete action with a selection', async ({ mount }) => {
    const component = await mountListing(mount);

    await expect(deleteSelection(component)).toBeDisabled();
    await selectRows(component);
    await expect(deleteSelection(component)).toBeEnabled();
  });

  test('asks for a confirmation', async ({ mount, page }) => {
    const component = await mountListing(mount);
    await selectRows(component);
    await deleteSelection(component).click();

    const dialog = page.getByRole('dialog');
    for (const text of [
      labelDeleteResourceAccessRules,
      labelDeleteResourceAccessRulesDialogMessage,
      labelDeleteResourceAccessRulesWarning
    ]) {
      await expect(dialog.getByText(text)).toBeVisible();
    }
    await dialog.getByText(labelCancel).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
  });

  const outcomes = [
    {
      message: labelResourceAccessRulesDeletedSuccess,
      name: 'confirms a successful deletion',
      response: 'success' as const,
      status: 207
    },
    {
      message: `${labelFailedToDeleteSelectedRules}: rule0, rule1`,
      name: 'names the rules that could not be deleted',
      response: 'warning' as const,
      status: 200
    },
    {
      message: labelFailedToDeleteSelectedRules,
      name: 'reports a failed deletion',
      response: 'failed' as const,
      status: 200
    }
  ];

  for (const { message, name, response, status } of outcomes) {
    test(name, async ({ mockApi, mount, page }) => {
      const deletion = await mockApi({
        endpoint: `${rulesEndpoint}/_delete`,
        method: 'POST',
        response: data.bulkDeletion[response],
        status
      });
      const component = await mountListing(mount);
      await selectRows(component);
      await deleteSelection(component).click();
      await page
        .getByRole('dialog')
        .getByText(labelDelete, { exact: true })
        .click();

      await expect.poll(() => deletion.urls.length).toBe(1);
      await expect(page.getByText(message, { exact: true })).toBeVisible();
    });
  }
});

test.describe('duplication', () => {
  let duplication: { urls: Array<URL> };

  test.beforeEach(async ({ mockApi }) => {
    await mockApi({
      endpoint: `${rulesEndpoint}/1`,
      response: findResourceAccessRuleResponse()
    });
    duplication = await mockApi({
      endpoint: rulesEndpoint,
      method: 'POST',
      response: { status: 'ok' }
    });
  });

  const openDuplication = async (component: Locator, page: Page) => {
    await component.getByTestId(labelDuplicate).first().click();
    const dialog = page.getByRole('dialog');
    await expect(
      dialog.getByText(labelEnterNameForDuplicatedRule)
    ).toBeVisible();

    return dialog;
  };

  test('validates the name of the copy', async ({ mount, page }) => {
    const component = await mountListing(mount);
    const dialog = await openDuplication(component, page);
    const name = dialog.getByLabel(labelResourceAccessRuleName);
    await expect(name).toBeVisible();

    await name.clear();
    await dialog.getByText(labelEnterNameForDuplicatedRule).click();
    await expect(dialog.getByText(labelRequired)).toBeVisible();

    await name.fill('rule0');
    await dialog.getByText(labelEnterNameForDuplicatedRule).click();
    await expect(dialog.getByText(labelNameAlreadyExists)).toBeVisible();

    await dialog.getByText(labelCancel).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
  });

  test('reports a successful duplication', async ({ mount, page }) => {
    const component = await mountListing(mount);
    const dialog = await openDuplication(component, page);
    await dialog.getByLabel(labelResourceAccessRuleName).fill('my_rule');
    await dialog.getByText(labelDuplicate, { exact: true }).click();

    await expect.poll(() => duplication.urls.length).toBe(1);
    await expect(page.getByText(labelRuleDuplicatedSuccess)).toBeVisible();
  });

  test('reports a failed duplication', async ({ mockApi, mount, page }) => {
    const failure = await mockApi({
      endpoint: rulesEndpoint,
      method: 'POST',
      response: { message: 'internal server error' },
      status: 500
    });
    const component = await mountListing(mount);
    const dialog = await openDuplication(component, page);
    await dialog.getByLabel(labelResourceAccessRuleName).fill('my_rule');
    await dialog.getByText(labelDuplicate, { exact: true }).click();

    await expect.poll(() => failure.urls.length).toBe(1);
    await expect(page.getByText('internal server error')).toBeVisible();
  });
});
