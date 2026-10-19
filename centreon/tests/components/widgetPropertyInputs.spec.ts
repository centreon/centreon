import type { Locator } from '@playwright/test';

import {
  labelBackgroundColor,
  labelBottom,
  labelDashboardGlobalInterval,
  labelDisplay,
  labelDisplayAs,
  labelHosts,
  labelMaxValue,
  labelMinMustLowerThanMax,
  labelMinValue,
  labelNumberOfValues,
  labelRefreshInterval,
  labelShowValueLabels,
  labelTop,
  labelValueFormat
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from the Cypress specs of
// Dashboards/SingleInstancePage/Dashboard/AddEditWidget/WidgetProperties/Inputs.

test.describe('Date picker', () => {
  const storyId = 'DatePicker/DatePicker/Default';
  const user = { locale: 'fr_FR', timezone: 'Europe/London' };

  test.beforeEach(async ({ page }) => {
    await page.clock.setFixedTime(new Date('2024-10-29T00:00:00Z'));
  });

  test('picks a date and a time', async ({ mount, page }) => {
    const component = await mount(storyId, { user });

    await component.getByTestId('CalendarIcon').click();
    const picker = page.getByRole('dialog');
    await picker.getByRole('gridcell', { name: '31' }).click();
    await picker.getByRole('option', { exact: true, name: '1 hours' }).click();
    await picker
      .getByRole('option', { exact: true, name: '40 minutes' })
      .click();
    // The hour list scroll animation never settles under a fixed clock, so
    // OK is pressed with the keyboard instead of waiting for it to be stable.
    await picker.getByRole('button', { name: 'OK' }).press('Enter');

    await expect(component.getByRole('textbox')).not.toHaveValue('');
    await expect(component.getByRole('textbox')).toHaveValue(/^31\/10\/2024/);
  });

  test('displays the saved date and time', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      options: { test: 1722366095000 },
      user
    });

    await expect(component.getByRole('textbox')).toHaveValue(
      '30/07/2024 20:01'
    );

    await expectThemedScreenshot(component, 'date-picker');
  });
});

test.describe('Value format', () => {
  const storyId = 'ValueFormat/ValueFormat/Default';
  const radio = (component: import('@playwright/test').Locator, id: string) =>
    component.getByTestId(id).locator('input');

  test('checks the saved format and switches to another one', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options: { format: 'human' } });

    await expect(component.getByText(labelValueFormat)).toBeVisible();
    await expect(radio(component, 'human')).toBeChecked();
    await expect(radio(component, 'raw')).not.toBeChecked();
    await expectThemedScreenshot(component, 'value-format');

    await component.getByTestId('raw').click();

    await expect(radio(component, 'raw')).toBeChecked();
    await expect(radio(component, 'human')).not.toBeChecked();
    await expect(component.getByTestId('form-values')).toHaveValue(
      /"format":"raw"/
    );
  });

  const lockedStates = [
    { canEdit: true, isEditing: false, name: 'while not editing' },
    { canEdit: false, isEditing: false, name: 'without edition rights' }
  ];

  for (const { canEdit, isEditing, name } of lockedStates) {
    test(`disables the formats ${name}`, async ({ mount }) => {
      const component = await mount(storyId, {
        canEdit,
        isEditing,
        options: { format: 'human' }
      });

      await expect(radio(component, 'human')).toBeDisabled();
      await expect(radio(component, 'raw')).toBeDisabled();
    });
  }
});

test.describe('Color selector', () => {
  const storyId = 'Color/Color/Default';

  test('displays the default color', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId);

    await expect(component.getByTestId('color-chip-#255891')).toBeVisible();
    await expect(component.getByText(labelBackgroundColor)).toBeVisible();

    await expectThemedScreenshot(component, 'color-selector');
  });

  test('selects another color', async ({ mount, page }) => {
    const component = await mount(storyId, {
      options: { test: '#1588D1' }
    });

    await component.getByTestId('color selector').click();
    // The palette is portalled; the chip sits in a button.
    await page
      .getByRole('button')
      .filter({ has: page.getByTestId('color-chip-#076059') })
      .click();

    await expect(component.getByTestId('color-chip-#076059')).toBeVisible();
  });
});

test.describe('Select', () => {
  const storyId = 'Select/Select/Default';

  test('selects another option', async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(storyId, {
      options: { select: 'option1' }
    });
    const select = component.getByTestId('Select');

    await expect(select).toHaveValue('option1');
    await expectThemedScreenshot(component, 'select');

    await component.getByRole('combobox').click();
    await page.getByRole('option', { name: 'Option 2' }).click();

    await expect(select).toHaveValue('option2');
  });
});

test.describe('Boundaries', () => {
  const storyId = 'Inputs/Boundaries/Default';

  test('writes the edited boundaries', async ({ mount }) => {
    const component = await mount(storyId);

    await component.getByLabel(labelMinValue).fill('20');
    await component.getByLabel(labelMaxValue).fill('30');

    await expect(component.getByLabel(labelMinValue)).toHaveValue('20');
    await expect(component.getByLabel(labelMaxValue)).toHaveValue('30');
  });

  test('rejects a max lower than the min', async ({ mount }) => {
    const component = await mount(storyId);

    await component.getByLabel(labelMinValue).fill('30');
    await component.getByLabel(labelMaxValue).fill('20');
    await component.getByLabel(labelMaxValue).blur();

    await expect(component.getByText(labelMinMustLowerThanMax)).toBeVisible();
  });
});

test.describe('Display type', () => {
  const storyId = 'DisplayType/DisplayType/Default';
  const options = { displayType: 'text' };

  test('selects another display type', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options });
    const type = (id: string) => component.locator(`[data-type="${id}"]`);

    await expect(component.getByText(labelDisplayAs)).toBeVisible();
    await expect(type('text')).toHaveAttribute('data-selected', 'true');
    await expectThemedScreenshot(component, 'display-type');

    await type('gauge').click();

    await expect(type('gauge')).toHaveAttribute('data-selected', 'true');
    await expect(type('text')).toHaveAttribute('data-selected', 'false');
    await expect(type('bar')).toHaveAttribute('data-selected', 'false');
  });
});

test.describe('Refresh interval', () => {
  const storyId = 'RefreshInterval/RefreshInterval/Default';
  const options = { refreshInterval: 'default', refreshIntervalCount: null };

  const globalIntervals = [
    { expected: '15 seconds', interval: null, type: 'global' as const },
    { expected: '50 seconds', interval: 50, type: 'global' as const },
    { expected: 'manual', interval: null, type: 'manual' as const }
  ];

  for (const { expected, interval, type } of globalIntervals) {
    test(`shows the dashboard interval (${expected})`, async ({ mount }) => {
      const component = await mount(storyId, {
        dashboardRefreshInterval: { interval, type },
        options
      });

      await expect(
        component.getByText(`${labelDashboardGlobalInterval} (${expected})`)
      ).toBeVisible();
    });
  }

  test('pluralizes the custom interval unit', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      dashboardRefreshInterval: { interval: null, type: 'global' },
      options
    });

    await expect(
      component.getByText(labelRefreshInterval, { exact: true })
    ).toBeVisible();
    await expectThemedScreenshot(component, 'refresh-interval');

    await component.getByTestId('custom').click();
    await component.getByTestId('intervalInput').fill('2');

    await expect(component.getByText('seconds', { exact: true })).toBeVisible();
    await expect(component.getByText('second', { exact: true })).toHaveCount(0);
  });
});

test.describe('Radio', () => {
  test('checks the clicked option', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount('Radio/Radio/Default', {
      options: { radio: [] }
    });
    const optionA = component.getByRole('radio', { name: 'A' });

    await expect(component.getByText('Title')).toBeVisible();
    await expect(optionA).not.toBeChecked();
    await expectThemedScreenshot(component, 'radio');

    await optionA.click();

    await expect(optionA).toBeChecked();
  });

  test('displays no option when the list is empty', async ({ mount }) => {
    const component = await mount('Radio/Radio/WithoutOptions', {
      options: { radio: [] }
    });

    await expect(component.getByRole('radio')).toHaveCount(0);
  });
});

test.describe('Top bottom settings', () => {
  const storyId = 'TopBottomSettings/TopBottomSettings/Default';
  const options = {
    topBottomSettings: { numberOfValues: 10, order: 'top', showLabels: true }
  };

  test('displays the saved settings', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, { options });

    await expect(component.getByText(labelDisplay)).toBeVisible();
    await expect(component.getByText(labelHosts)).toBeVisible();
    await expect(component.getByLabel(labelNumberOfValues)).toHaveValue('10');
    await expect(component.getByTestId(labelTop)).toHaveAttribute(
      'aria-pressed',
      'true'
    );
    await expect(component.getByLabel(labelShowValueLabels)).toBeChecked();

    await expectThemedScreenshot(component, 'top-bottom-settings');
  });

  test('switches the order to bottom', async ({ mount }) => {
    const component = await mount(storyId, { options });

    await component.getByTestId(labelBottom).click();

    await expect(component.getByTestId(labelBottom)).toHaveAttribute(
      'aria-pressed',
      'true'
    );
    await expect(component.getByTestId(labelTop)).toHaveAttribute(
      'aria-pressed',
      'false'
    );
  });

  const numberOfValues = [
    { expected: '5', typed: '5' },
    { expected: '1', typed: '0' }
  ];

  for (const { expected, typed } of numberOfValues) {
    test(`stores ${expected} when ${typed} is typed`, async ({ mount }) => {
      const component = await mount(storyId, { options });
      const field = component.getByLabel(labelNumberOfValues);

      await field.fill(typed);

      await expect(field).toHaveValue(expected);
    });
  }

  test('hides the value labels', async ({ mount }) => {
    const component = await mount(storyId, { options });

    await component.getByLabel(labelShowValueLabels).click();

    await expect(component.getByLabel(labelShowValueLabels)).not.toBeChecked();
  });
});

// The edition rights disable every input the same way.
const disabledInputs: Array<{
  field: (component: Locator) => Locator;
  props: Record<string, unknown>;
  storyId: string;
}> = [
  {
    field: (component) => component.getByRole('textbox'),
    props: { options: { test: null }, user: { locale: 'fr_FR' } },
    storyId: 'DatePicker/DatePicker/Default'
  },
  {
    field: (component) => component.getByTestId('color selector'),
    props: {},
    storyId: 'Color/Color/Default'
  },
  {
    field: (component) => component.getByTestId('Select'),
    props: { options: { select: 'option1' } },
    storyId: 'Select/Select/Default'
  },
  {
    field: (component) => component.getByLabel(labelMinValue),
    props: {},
    storyId: 'Inputs/Boundaries/Default'
  },
  {
    field: (component) => component.getByLabel(labelNumberOfValues),
    props: {
      options: {
        topBottomSettings: {
          numberOfValues: 10,
          order: 'top',
          showLabels: true
        }
      }
    },
    storyId: 'TopBottomSettings/TopBottomSettings/Default'
  }
];

for (const { field, props, storyId } of disabledInputs) {
  test(`${storyId} is disabled without edition rights`, async ({ mount }) => {
    const component = await mount(storyId, { ...props, canEdit: false });

    await expect(field(component)).toBeDisabled();
  });
}

test('Radio is disabled without edition rights', async ({ mount }) => {
  const component = await mount('Radio/Radio/Default', {
    canEdit: false,
    options: { radio: [] }
  });

  await expect(component.getByRole('radio', { name: 'A' })).toBeDisabled();
  await expect(component.getByRole('radio', { name: 'B' })).toBeDisabled();
});

test('Display type is disabled without edition rights', async ({ mount }) => {
  const component = await mount('DisplayType/DisplayType/Default', {
    canEdit: false,
    options: { displayType: 'text' }
  });

  for (const id of ['text', 'gauge', 'bar']) {
    await expect(component.locator(`[data-type="${id}"]`)).toHaveAttribute(
      'data-disabled',
      'true'
    );
  }
});
