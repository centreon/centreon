import { expect, test } from '../fixtures';

/**
 * packages/ui inputs and containers: Number, SegmentedButtons,
 * CollapsibleItem and the time period selector.
 */
test.describe('Number field', () => {
  const number = 'ui/InputField/Number/Number/Default';

  for (const { expected, label, props } of [
    { expected: '0', label: 'zero', props: {} },
    {
      expected: '25',
      label: 'the fallback value',
      props: { fallbackValue: 25 }
    }
  ]) {
    test(`shows ${label} as placeholder without a value`, async ({ mount }) => {
      const component = await mount(number, props);
      const input = component.locator('input');
      await expect(input).toHaveValue('');
      await expect(input).toHaveAttribute('placeholder', expected);
    });
  }

  test('falls back to the fallback value once cleared', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(number, {
      defaultValue: 25,
      fallbackValue: 10
    });
    const input = component.locator('input');
    await expect(input).toHaveValue('25');
    await input.clear();
    await expect(input).toHaveValue('');
    await expect(input).toHaveAttribute('placeholder', '10');
    await expect(component.getByTestId('number-value')).toHaveText('10');
    await expectThemedScreenshot(component, 'number-cleared');
  });

  test('sizes itself to its value in auto size', async ({ mount }) => {
    const component = await mount(number, { autoSize: true, defaultValue: 25 });
    const input = component.locator('input');
    await input.pressSequentially('000');
    await expect(input).toHaveValue('25000');
    // Without auto size, the field takes the whole width of the gallery.
    expect((await input.boundingBox())?.width).toBeLessThan(200);
  });

  test('raises a value under the minimum to the minimum', async ({ mount }) => {
    const component = await mount(number, { defaultValue: 25, min: 2 });
    const input = component.locator('input');
    await input.fill('-1');
    await expect(input).toHaveValue('2');
    await expect(component.getByTestId('number-value')).toHaveText('2');
  });
});

test.describe('Segmented buttons', () => {
  const segmented = 'ui/Form/Inputs/SegmentedButtons/Default';

  test('stores the value of the clicked option as given', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(segmented);
    const button = (name: string) =>
      component.getByRole('button', { exact: true, name });
    await expect(component.getByText('Notification Enabled')).toBeVisible();
    await expect(button('Default')).toHaveAttribute('aria-pressed', 'true');
    await expect(button('Yes')).toHaveAttribute('aria-pressed', 'false');

    await button('Yes').click();
    // A string, not the boolean a radio would store.
    await expect(component.getByTestId('value')).toHaveText('"true"');
    await expect(button('Yes')).toHaveAttribute('aria-pressed', 'true');
    await expect(button('Default')).toHaveAttribute('aria-pressed', 'false');
    await button('No').click();
    await expect(component.getByTestId('value')).toHaveText('"false"');
    await expectThemedScreenshot(component, 'segmented-buttons');
  });

  test('starts on the initial value', async ({ mount }) => {
    const component = await mount(segmented, { enabled: 'false' });
    await expect(component.getByRole('button', { name: 'No' })).toHaveAttribute(
      'aria-pressed',
      'true'
    );
  });

  test('does not change when disabled', async ({ mount }) => {
    const component = await mount(segmented, { disabled: true });
    await expect(component.getByRole('button', { name: 'Yes' })).toBeDisabled();
    await expect(component.getByTestId('value')).toHaveText('"use_default"');
  });
});

test.describe('CollapsibleItem', () => {
  const item = 'ui/components/CollapsibleItem/CollapsibleItem/Default';

  for (const compact of [false, true]) {
    test(`expands its content on demand${compact ? ' when compact' : ''}`, async ({
      expectThemedScreenshot,
      mount
    }) => {
      const component = await mount(item, { compact });
      await expect(component.getByText('Title')).toBeVisible();
      await expect(component.getByText('Content')).toBeHidden();
      await expect(component.locator('div[data-compact="true"]')).toHaveCount(
        compact ? 1 : 0
      );

      await component.locator('button[aria-expanded="false"]').click();
      await expect(
        component.locator('button[aria-expanded="true"]')
      ).toBeVisible();
      await expect(component.getByText('Content')).toBeVisible();
      await expectThemedScreenshot(
        component,
        `collapsible-item${compact ? '-compact' : ''}`
      );
    });
  }

  test('starts expanded on demand, with a custom title', async ({ mount }) => {
    const component = await mount(item, {
      customTitle: true,
      defaultExpanded: true
    });
    await expect(component.getByText('Customized title')).toBeVisible();
    await expect(component.getByText('Content')).toBeVisible();
    await expect(
      component.locator('button[aria-expanded="true"]')
    ).toBeVisible();
  });
});

test.describe('Time period selector', () => {
  // The selected period ends now: freeze it for the screenshots.
  test.beforeEach(async ({ page }) => {
    await page.clock.setFixedTime(new Date('2024-06-15T10:00:00Z'));
  });

  for (const width of [200, 500, 1024]) {
    test(`fits the periods and dates in ${width}px`, async ({
      expectThemedScreenshot,
      mount,
      page
    }) => {
      await page.setViewportSize({ height: 590, width });
      const component = await mount('ui/TimePeriods/ResolutionTimePeriod/Default');
      for (const name of ['Last day', 'Last 7 days', 'Last 31 days']) {
        await expect(component.getByRole('button', { exact: true, name })).toBeVisible();
      }
      await expect(
        component.getByRole('button', { name: 'Compact time period' })
      ).toHaveText(
        // 10:00 UTC in the Europe/Paris timezone of the user.
        'From:06/14/2024 12:00 PMTo:06/15/2024 12:00 PM'
      );
      await expectThemedScreenshot(component, `time-period-${width}`);
    });
  }
});
