import {
  labelSelectAll,
  labelUnselectAll
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/AddEditWidget/WidgetProperties/Inputs/Checboxes/translatedLabels';
import {
  labelCriticalThreshold,
  labelCustomize,
  labelFrom,
  labelShowThresholds,
  labelThresholds,
  labelTimePeriod,
  labelTo,
  labelWarningThreshold
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from the ButtonGroup, TextField, Slider, Checkboxes and Threshold
// Cypress specs of Dashboards/.../AddEditWidget/WidgetProperties/Inputs.

test.describe('Button group', () => {
  const storyId = 'ButtonGroup/ButtonGroup/Default';
  const options = { test: 'button1' };

  test('selects the clicked button', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options });
    const button = (name: string) =>
      component.getByLabel(name, { exact: true });

    await expect(button('Button 1')).toHaveAttribute('data-selected', 'true');
    await expect(button('Button 2')).toHaveAttribute('data-selected', 'false');
    await expectThemedScreenshot(component, 'button-group');

    await button('Button 2').click();

    await expect(button('Button 1')).toHaveAttribute('data-selected', 'false');
    await expect(button('Button 2')).toHaveAttribute('data-selected', 'true');
  });

  test('displays its title in a group', async ({ mount }) => {
    const component = await mount(storyId, {
      inputProps: { isInGroup: true },
      options
    });

    await expect(component.getByText('Buttons')).toBeVisible();
  });

  test('displays the secondary label on hover', async ({ mount, page }) => {
    const component = await mount(storyId, {
      inputProps: { secondaryLabel: 'This is a secondary label' },
      options
    });

    await component.getByTestId('secondary-label-test').hover();

    await expect(page.getByText('This is a secondary label')).toBeVisible();
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, { canEdit: false, options });

    for (const name of ['Button 1', 'Button 2', 'Button 3']) {
      await expect(component.getByLabel(name, { exact: true })).toBeDisabled();
    }
  });
});

test.describe('Text field', () => {
  const storyId = 'Inputs/TextField/Default';

  const variants = [
    { initial: 'Text', name: 'text', text: undefined, typed: 'updated text' },
    {
      initial: 'Text',
      name: 'compact text',
      text: { autoSize: true, size: 'compact' },
      typed: 'updated text'
    },
    { initial: 2, name: 'number', text: { type: 'number' }, typed: '34' },
    {
      initial: 2,
      name: 'compact number',
      text: { autoSize: true, size: 'compact', type: 'number' },
      typed: '34'
    }
  ];

  for (const { initial, name, text, typed } of variants) {
    test(`edits a ${name} field`, async ({ mount }) => {
      const component = await mount(storyId, {
        inputProps: { text },
        options: { test: initial }
      });
      const field = component.getByLabel('Text', { exact: true });

      await expect(field).toHaveValue(String(initial));
      await field.fill(typed);
      await expect(field).toHaveValue(typed);
    });
  }

  test('clamps a number to its boundaries on blur', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      inputProps: {
        text: {
          autoSize: true,
          max: 20,
          min: -2,
          size: 'compact',
          type: 'number'
        }
      },
      options: { test: 2 }
    });
    const field = component.getByLabel('Text', { exact: true });

    await field.fill('34');
    await field.blur();
    await expect(field).toHaveValue('20');

    await field.fill('-5');
    await field.blur();
    await expect(field).toHaveValue('-2');

    await field.fill('10');
    await expect(field).toHaveValue('10');
    await expectThemedScreenshot(component, 'text-field-number');
  });
});

test.describe('Slider', () => {
  const storyId = 'Slider/Slider/Default';
  const options = { test: 3 };

  test('moves the thumb with the keyboard', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options });
    const thumb = component.getByRole('slider');

    await expect(component.getByLabel('slider-test-input')).toHaveValue('3');
    await expectThemedScreenshot(component, 'slider');

    await thumb.focus();
    await thumb.press('ArrowRight');
    await thumb.press('ArrowRight');
    await thumb.press('ArrowRight');

    await expect(component.getByLabel('slider-test-input')).toHaveValue('6');
  });

  test('follows the value typed in the input', async ({ mount }) => {
    const component = await mount(storyId, { options });

    await component.getByLabel('slider-test-input').fill('30');

    await expect(component.getByRole('slider')).toHaveValue('30');
  });

  test('clamps the typed value to its boundaries', async ({ mount }) => {
    const component = await mount(storyId, {
      inputProps: { slider: { max: 30, min: -30 } },
      options
    });
    const input = component.getByLabel('slider-test-input');

    await input.fill('31');
    await expect(input).toHaveValue('30');
    await input.fill('-31');
    await expect(input).toHaveValue('-30');
  });

  test('displays its unit', async ({ mount }) => {
    const component = await mount(storyId, {
      inputProps: { slider: { max: 30, min: -30, unit: '•••' } },
      options
    });

    await expect(component.getByText('•••')).toBeVisible();
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, { canEdit: false, options });

    await expect(component.getByLabel('slider-test-input')).toBeDisabled();
    await expect(component.getByRole('slider')).toBeDisabled();
  });
});

test.describe('Checkboxes', () => {
  const storyId = 'Checboxes/Checkboxes/Default';
  const checkbox = (
    component: import('@playwright/test').Locator,
    name: string
  ) => component.getByRole('checkbox', { exact: true, name });

  test('selects and unselects all the options', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options: { checkbox: [] } });

    await expect(component.getByText('Title')).toBeVisible();
    await expect(checkbox(component, 'A')).not.toBeChecked();
    await expectThemedScreenshot(component, 'checkboxes');

    await component.getByText(labelSelectAll).click();
    await expect(checkbox(component, 'A')).toBeChecked();
    await expect(checkbox(component, 'B')).toBeChecked();

    await component.getByText(labelUnselectAll).click();
    await expect(checkbox(component, 'A')).not.toBeChecked();
    await expect(checkbox(component, 'B')).not.toBeChecked();
  });

  test('checks the clicked option', async ({ mount }) => {
    const component = await mount(storyId, { options: { checkbox: [] } });

    await checkbox(component, 'A').click();

    await expect(checkbox(component, 'A')).toBeChecked();
  });

  test('displays every secondary label on hover', async ({ mount, page }) => {
    const labels = ['Secondary label', 'other label'];
    const component = await mount(storyId, {
      inputProps: { secondaryLabel: labels },
      options: { checkbox: [] }
    });

    await component.getByLabel(labels[0]).hover();

    for (const label of labels) {
      await expect(page.getByText(label, { exact: true })).toBeVisible();
    }
  });

  test('keeps one option selected when required', async ({ mount }) => {
    const component = await mount(storyId, {
      inputProps: { keepOneOptionSelected: true },
      options: { checkbox: ['a', 'b'] }
    });

    await checkbox(component, 'B').click();
    await expect(checkbox(component, 'A')).toBeChecked();
    await checkbox(component, 'A').click();
    await expect(checkbox(component, 'A')).toBeChecked();
  });

  test('checks options of a form without values', async ({ mount }) => {
    const component = await mount(storyId, { options: null });

    await checkbox(component, 'A').click();
    await checkbox(component, 'B').click();
    await expect(checkbox(component, 'A')).toBeChecked();
    await expect(checkbox(component, 'B')).toBeChecked();

    await checkbox(component, 'A').click();
    await expect(checkbox(component, 'A')).not.toBeChecked();
  });

  test('displays nothing without options', async ({ mount }) => {
    const component = await mount('Checboxes/Checkboxes/WithoutOptions', {
      options: { checkbox: [] }
    });

    await expect(component.getByText(labelSelectAll)).toHaveCount(0);
    await expect(component.getByRole('checkbox')).toHaveCount(0);
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, {
      canEdit: false,
      options: { checkbox: [] }
    });

    await expect(
      component.getByRole('button', { name: labelSelectAll })
    ).toBeDisabled();
    await expect(checkbox(component, 'A')).toBeDisabled();
    await expect(checkbox(component, 'B')).toBeDisabled();
  });
});

test.describe('Threshold', () => {
  const storyId = 'Threshold/Threshold/Default';
  const threshold = (enabled: boolean) => ({
    threshold: {
      criticalType: 'default',
      customCritical: null,
      customWarning: null,
      enabled,
      warningType: 'default'
    }
  });
  const ping = {
    id: 1,
    metrics: [
      {
        criticalHighThreshold: 100,
        criticalLowThreshold: 50,
        id: 1,
        name: 'rta',
        unit: 'ms',
        warningHighThreshold: 35,
        warningLowThreshold: 10
      }
    ],
    name: 'Server_Ping'
  };
  const cpu = {
    id: 2,
    metrics: [
      {
        criticalHighThreshold: 100,
        criticalLowThreshold: null,
        id: 2,
        name: 'idle',
        unit: '%',
        warningHighThreshold: 60,
        warningLowThreshold: null
      }
    ],
    name: 'Server_Cpu'
  };
  const withMetrics = (metrics?: Array<unknown>) => ({
    extraValues: { data: { metrics } }
  });

  test('has no default values without metrics', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      ...withMetrics([]),
      options: threshold(true)
    });

    await expect(
      component.getByText(labelThresholds, { exact: true })
    ).toBeVisible();
    await expect(component.getByText(labelWarningThreshold)).toBeVisible();
    await expect(component.getByText(labelCriticalThreshold)).toBeVisible();
    await expect(component.getByLabel(labelShowThresholds)).toBeChecked();
    await expect(component.getByText('Default (none)').first()).toBeVisible();
    await expect(component.getByTestId(labelThresholds)).toHaveCount(0);

    await expectThemedScreenshot(component, 'threshold');
  });

  test('defaults to the thresholds of the first metric', async ({ mount }) => {
    const component = await mount(storyId, {
      ...withMetrics([ping]),
      options: threshold(true)
    });

    await expect(component.getByText('Default (10 ms - 35 ms)')).toBeVisible();
    await expect(component.getByText('Default (50 ms - 100 ms)')).toBeVisible();
  });

  test('enables a custom threshold', async ({ mount }) => {
    const component = await mount(storyId, {
      ...withMetrics([ping]),
      options: threshold(false)
    });

    await component.getByLabel(labelShowThresholds).click();
    await component.getByTestId('custom').first().click();
    const customValue = component.getByTestId(labelThresholds).locator('input');

    await expect(customValue).toHaveCount(1);
    await expect(customValue).toBeEnabled();
    await customValue.fill('50');
    await expect(component.getByText('(50 ms)', { exact: true })).toBeVisible();
  });

  test('turns the thresholds off for metrics of different units', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      ...withMetrics([ping, cpu]),
      options: threshold(true)
    });

    await expect(component.getByLabel(labelShowThresholds)).not.toBeChecked();
  });

  test('does not display none when the metrics are undefined', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      ...withMetrics(undefined),
      options: threshold(true)
    });

    await expect(component.getByText('Default').first()).toBeVisible();
    await expect(component.getByText('(none)')).toHaveCount(0);
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, {
      ...withMetrics([ping]),
      canEdit: false,
      options: threshold(true)
    });

    await expect(component.getByLabel(labelShowThresholds)).toBeDisabled();
    await expect(
      component.getByTestId('default').first().locator('input')
    ).toBeDisabled();
    await expect(
      component.getByTestId('custom').first().locator('input')
    ).toBeDisabled();
  });
});

test.describe('Time period', () => {
  const storyId = 'TimePeriod/TimePeriod/Default';
  const options = { timeperiod: { end: null, start: null } };
  // Hours, as declared in useTimePeriod.ts.
  const periods = [
    { hours: 3, name: 'Last 3 hours' },
    { hours: 6, name: 'Last 6 hours' },
    { hours: 12, name: 'Last 12 hours' },
    { hours: 24, name: 'Last 24 hours' },
    { hours: 168, name: 'Last 7 days' },
    { hours: 336, name: 'Last 14 days' },
    { hours: 720, name: 'Last 30 days' },
    { hours: 1440, name: 'Last 2 months' },
    { hours: 2160, name: 'Last 3 months' }
  ];

  test.use({ viewport: { height: 800, width: 1280 } });

  test.beforeEach(async ({ page }) => {
    await page.clock.setFixedTime(new Date('2023-06-05T08:00:00Z'));
  });

  test('preselects the last hour', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options });

    await expect(component.getByText(labelTimePeriod)).toBeVisible();
    await expect(component.getByTestId(labelTimePeriod)).toHaveValue('1');

    await expectThemedScreenshot(component, 'time-period');
  });

  for (const { hours, name } of periods) {
    test(`selects ${name.toLowerCase()}`, async ({ mount, page }) => {
      const component = await mount(storyId, { options });

      await component.getByRole('combobox').click();
      await page.getByRole('option', { exact: true, name }).click();

      await expect(component.getByTestId(labelTimePeriod)).toHaveValue(
        String(hours)
      );
    });
  }

  test('presets the custom range to the last hour', async ({ mount, page }) => {
    const component = await mount(storyId, { options });

    await component.getByRole('combobox').click();
    await page.getByRole('option', { name: labelCustomize }).click();

    await expect(component.getByLabel(labelFrom).locator('input')).toHaveValue(
      '06/05/2023 07:00 AM'
    );
    await expect(component.getByLabel(labelTo).locator('input')).toHaveValue(
      '06/05/2023 08:00 AM'
    );
  });

  test('is disabled without edition rights', async ({ mount }) => {
    const component = await mount(storyId, { canEdit: false, options });

    await expect(component.getByTestId(labelTimePeriod)).toBeDisabled();
  });

  test('renders its title as a subtitle out of a group', async ({ mount }) => {
    const outOfGroup = await mount(storyId, { options });
    await expect(outOfGroup.locator('h6')).toHaveCount(1);

    const inGroup = await mount(storyId, {
      inputProps: { isInGroup: true },
      options
    });
    await expect(inGroup.locator('h6')).toHaveCount(0);
  });
});
