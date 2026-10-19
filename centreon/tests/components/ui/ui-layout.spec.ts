import { expect, test } from '../fixtures';

/**
 * packages/ui layout components: FluidTypography, Tabs, PageLayout and the
 * dashboard layout. The Cypress specs repeated FluidTypography for each
 * prop and width with one snapshot each; the text fits each width here.
 */
test.describe('FluidTypography', () => {
  for (const width of [500, 300, 150]) {
    test(`keeps the text visible in ${width}px`, async ({ mount, page }) => {
      await page.setViewportSize({ height: 590, width });
      for (const props of [{ max: '80px' }, { min: '30px' }, { pref: 10 }]) {
        const component = await mount(
          'ui/Typography/FluidTypography/FluidTypography/Default',
          props
        );
        await expect(component.getByText('Unreachable')).toBeInViewport();
      }
    });
  }
});

test.describe('Tabs', () => {
  test('shows the panel of the selected tab', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('ui/components/Tabs/Tabs/Default');
    const panel = (value: string) =>
      component.locator(`[data-TabPanel="${value}"]`);
    const tab = (label: string) => component.getByLabel(label, { exact: true });

    await expect(panel('tab 0')).not.toHaveAttribute('hidden');
    await expect(panel('tab 1')).toHaveAttribute('hidden');
    await expect(tab('Tab 0')).toHaveAttribute('aria-selected', 'true');

    await component.getByText('Tab 1', { exact: true }).first().click();
    await expect(panel('tab 0')).toHaveAttribute('hidden');
    await expect(panel('tab 1')).not.toHaveAttribute('hidden');
    await expect(tab('Tab 1')).toHaveAttribute('aria-selected', 'true');
    await expectThemedScreenshot(component, 'tabs');
  });

  test('spreads the tabs with the tab list props', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('ui/components/Tabs/Tabs/Default', {
      fullWidth: true
    });
    await expect(
      component.locator('[data-TabPanel="tab 0"]')
    ).not.toHaveAttribute('hidden');
    await expectThemedScreenshot(component, 'tabs-full-width');
  });
});

test.describe('PageLayout', () => {
  test('opens the quick access of the header', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount('ui/components/Layout/PageLayout/Default');
    await expectThemedScreenshot(component, 'page-layout');

    await component.getByTestId('quickaccess').click();
    for (const entry of ['Entity', 'Create', 'Go back']) {
      await expect(page.getByText(entry, { exact: true })).toBeVisible();
    }
  });
});

test.describe('DashboardLayout', () => {
  test.use({ viewport: { height: 800, width: 1280 } });

  test('renders the panels in the viewport without placeholder', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('ui/Dashboard/Dashboard/Default');
    for (const panel of ['a', 'b', 'c']) {
      await expect(
        component.locator(`[data-widget-skeleton="${panel}"]`)
      ).toHaveCount(0);
    }
    await expect(component.getByText('Panel 1')).toBeVisible();
    await expectThemedScreenshot(component, 'dashboard-layout');
  });
});

test.describe('useLocale', () => {
  for (const { browserLocale, expected, userLocale } of [
    { browserLocale: 'en', expected: 'fi', userLocale: 'fi' },
    { browserLocale: 'de', expected: 'de', userLocale: null }
  ]) {
    test(`returns ${expected} for the user locale ${userLocale} and the browser locale ${browserLocale}`, async ({
      mount
    }) => {
      const component = await mount('ui/utils/useLocale/useLocale/Default', {
        browserLocale,
        userLocale
      });
      await expect(
        component.getByText(expected, { exact: true })
      ).toBeVisible();
    });
  }
});
