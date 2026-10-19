import { generateItems } from '../../../packages/ui/src/Pagination/utils';
import { expect, test } from '../fixtures';

/**
 * packages/ui ItemComposition, Module and LicensedModule, useFullscreen and
 * Pagination.
 */
test.describe('ItemComposition', () => {
  const composition = 'ui/components/ItemComposition/ItemComposition/Default';

  test('lists its items and calls back on add and delete', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(composition);
    await expect(component.getByText('Item 1')).toBeVisible();
    await expect(component.getByText('Item 2')).toBeVisible();
    await expect(component.getByTestId('Delete')).toHaveCount(2);
    await expectThemedScreenshot(component, 'item-composition');

    await component.getByTestId('Add').click();
    await component.getByTestId('Delete').nth(1).click();
    await expect(component.getByTestId('calls')).toHaveText(
      'add, delete Item 2'
    );
  });

  test('applies its options', async ({ mount }) => {
    const secondary = await mount(composition, {
      secondaryLabel: 'Secondary label'
    });
    await expect(secondary.getByText('Secondary label')).toBeVisible();

    const disabled = await mount(composition, { addbuttonDisabled: true });
    await expect(disabled.getByTestId('Add')).toBeDisabled();

    const noAdd = await mount(composition, { addButtonHidden: true });
    await expect(noAdd.getByTestId('Add')).toHaveCount(0);

    // Hidden, not removed: the items keep their layout.
    const noDelete = await mount(composition, { deleteButtonHidden: true });
    for (const button of await noDelete.getByTestId('Delete').all()) {
      await expect(button).toBeHidden();
    }

    const linked = await mount(composition, { displayItemsAsLinked: true });
    await expect(linked.locator('[data-linked]').first()).toBeVisible();
  });
});

test.describe('Module', () => {
  // The mocks ignore the query string: answer after the product name.
  const licenses = {
    endpoint: './api/internal.php',
    response: (url: URL) => ({
      success: url.searchParams.get('productName') === 'valid'
    })
  };

  test('renders its content', async ({ mount }) => {
    const component = await mount('ui/Module/Module/Default');
    await expect(component.getByText('Module')).toBeVisible();
  });

  for (const isFederatedComponent of [false, true]) {
    test(`renders a ${isFederatedComponent ? 'component' : 'page'} of a valid license only`, async ({
      expectThemedScreenshot,
      mockApi,
      mount
    }) => {
      const licensed = 'ui/Module/Module/Licensed';
      await mockApi(licenses);

      const valid = await mount(licensed, {
        isFederatedComponent,
        moduleName: 'valid'
      });
      await expect(valid.getByText('Module')).toBeVisible();

      const invalid = await mount(licensed, {
        isFederatedComponent,
        moduleName: 'invalid'
      });
      await expect(invalid.getByText('Module')).toHaveCount(0);
      if (!isFederatedComponent) {
        await expect(
          invalid.getByText('License invalid or expired')
        ).toBeVisible();
        await expect(
          invalid.getByText('Please contact your administrator.')
        ).toBeVisible();
        await expect(
          invalid.getByAltText('License invalid or expired !')
        ).toBeVisible();
        await expect(invalid.getByAltText('Centreon Logo')).toBeVisible();
        await expectThemedScreenshot(invalid, 'licensed-module-invalid');
      }
    });
  }
});

test.describe('useFullscreen', () => {
  const fullscreen = 'ui/utils/useFullscreen/Fullscreen/Default';

  test('toggles the fullscreen from the button and the F key', async ({
    mount,
    page
  }) => {
    const component = await mount(fullscreen);
    const state = component.locator('#test');
    await expect(state).toHaveAttribute('data-fullscreenEnabled', 'true');
    await expect(state).toHaveAttribute('data-fullscreenActivated', 'false');

    await component.getByRole('button', { name: 'Enter fullscreen' }).click();
    await expect(state).toHaveAttribute('data-fullscreenActivated', 'true');
    await component.getByRole('button', { name: 'Exit fullscreen' }).click();
    await expect(state).toHaveAttribute('data-fullscreenActivated', 'false');

    await page.keyboard.press('F');
    await expect(state).toHaveAttribute('data-fullscreenActivated', 'true');
    await page.keyboard.press('F');
    await expect(state).toHaveAttribute('data-fullscreenActivated', 'false');
  });

  for (const field of ['input', 'textarea']) {
    test(`types F in a focused ${field} instead of toggling`, async ({
      mount,
      page
    }) => {
      const component = await mount(fullscreen);
      await component.locator(`#${field}`).focus();
      await page.keyboard.press('F');
      await expect(component.locator(`#${field}`)).toHaveValue('F');
      await expect(component.locator('#test')).toHaveAttribute(
        'data-fullscreenActivated',
        'false'
      );
    });
  }
});

test.describe('Pagination', () => {
  const pagination = 'ui/Pagination/Pagination/Default';
  const listing = (total: number) => ({
    endpoint: 'test/listing',
    response: { meta: { limit: 6, page: 1, total }, result: generateItems(6) }
  });

  test('pages forward and backward through the results', async ({
    expectThemedScreenshot,
    mockApi,
    mount
  }) => {
    const requests = await mockApi(listing(25));
    const component = await mount(pagination);
    const next = component.getByTestId('Next page');
    const previous = component.getByTestId('Previous page');

    await expect(component.getByText('Page 1/5')).toBeVisible();
    await expect(previous).toBeDisabled();
    await expectThemedScreenshot(component, 'pagination');
    for (const page of [2, 3, 4, 5]) {
      await next.click();
      await expect(component.getByText(`Page ${page}/5`)).toBeVisible();
      await expect(previous).toBeEnabled();
    }
    await expect(next).toBeDisabled();
    for (const page of [4, 3, 2, 1]) {
      await previous.click();
      await expect(component.getByText(`Page ${page}/5`)).toBeVisible();
    }
    await expect(previous).toBeDisabled();
    await expect.poll(() => requests.urls.length).toBeGreaterThanOrEqual(5);
  });

  test('hides its controls for a single page', async ({ mockApi, mount }) => {
    const requests = await mockApi(listing(6));
    const component = await mount(pagination);
    await expect.poll(() => requests.urls.length).toBeGreaterThan(0);
    await expect(component.getByTestId('Next page')).toHaveCount(0);
    await expect(component.getByTestId('Previous page')).toHaveCount(0);
    await expect(component.getByText(/Page \d+\/\d+/)).toHaveCount(0);
  });
});
