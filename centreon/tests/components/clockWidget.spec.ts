import { expect, test } from './fixtures';

// Migrated from Dashboards/.../Widgets/centreon-widget-clock/src/spec/Clock.
const storyId = 'centreon-widget-clock/src/Widget/Default';
const clock = {
  displayType: 'clock',
  showDate: true,
  showTimezone: true
} as const;
const timer = {
  displayType: 'timer',
  showDate: true,
  showTimezone: true
} as const;
const london = {
  locale: { id: 'fr-FR', name: 'French' },
  timeFormat: '24',
  timezone: { id: 'Europe/London', name: 'Europe/London' }
} as const;

test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(1722584072000);
});

test.describe('Clock', () => {
  test('displays the time of the user', async ({
    expectThemedScreenshot,
    mount
  }) => {
    const component = await mount(storyId, {
      panelOptions: { ...clock, timeFormat: '12' as const }
    });

    await expect(component.getByText('Europe/Helsinki')).toBeVisible();
    await expect(component.getByTestId('QueryBuilderIcon')).toBeVisible();
    await expect(component.getByText('02.08.2024')).toBeVisible();
    await expect(component.getByText('10:34')).toBeVisible();
    await expect(component.getByText('AM')).toBeVisible();

    await expectThemedScreenshot(component, 'clock');
  });

  test('moves the timezone and date to icons when small', async ({ mount }) => {
    const component = await mount(storyId, {
      isSmall: true,
      panelOptions: { ...clock, timeFormat: '12' as const }
    });

    await expect(component.getByText('Europe/Helsinki')).toHaveCount(0);
    await expect(component.getByTestId('PublicIcon')).toBeVisible();
    await expect(component.getByLabel('Europe/Helsinki')).toBeVisible();
    await expect(component.getByLabel('02.08.2024')).toBeVisible();
    await expect(component.getByText('10:34')).toBeVisible();
  });

  test('hides the date and the timezone', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: {
        ...clock,
        showDate: false,
        showTimezone: false,
        timeFormat: '12' as const
      }
    });

    await expect(component.getByText('Europe/Helsinki')).toHaveCount(0);
    await expect(component.getByText('02.08.2024')).toHaveCount(0);
  });

  test('uses the configured timezone, locale and format', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: { ...clock, ...london }
    });

    await expect(component.getByText('Europe/London')).toBeVisible();
    await expect(component.getByText('02/08/2024')).toBeVisible();
    await expect(component.getByText('08:34')).toBeVisible();
  });

  test('renders with a background color and a description', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      hasDescription: true,
      panelOptions: { ...clock, ...london, backgroundColor: '#456373' }
    });

    await expect(component.getByText('Europe/London')).toBeVisible();
  });

  test('falls back to the browser locale without user locale', async ({
    mount
  }) => {
    const component = await mount(storyId, {
      panelOptions: { ...clock, timeFormat: '12' as const },
      userLocale: null
    });

    await expect(component.getByText('08/02/2024')).toBeVisible();
    await expect(component.getByText('10:34')).toBeVisible();
  });
});

test.describe('Timer', () => {
  // No screenshot: the fluid digits are resized asynchronously.
  test('counts down to the end date', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: { ...timer, countdown: 1722587072000 }
    });

    await expect(component.getByText('Europe/Helsinki')).toBeVisible();
    await expect(component.getByTestId('HourglassTopIcon')).toBeVisible();
    await expect(
      component.getByText('Ends at: 02.08.2024 11:24')
    ).toBeVisible();
    await expect(component.getByText('00:50:00')).toBeVisible();
    for (const unit of ['Hours', 'Minutes', 'Seconds']) {
      await expect(component.getByText(unit)).toBeVisible();
    }
  });

  test('hides the date and the timezone', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: {
        ...timer,
        countdown: 1722587072000,
        showDate: false,
        showTimezone: false
      }
    });

    await expect(component.getByText('Europe/Helsinki')).toHaveCount(0);
    await expect(component.getByText('02.08.2024')).toHaveCount(0);
  });

  test('displays days for a far end date', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: { ...timer, countdown: 1723830148000, showTimezone: false }
    });

    await expect(
      component.getByText('Ends at: 16.08.2024 20:42')
    ).toBeVisible();
    await expect(component.getByText('14:10:07')).toBeVisible();
    for (const unit of ['Days', 'Hours', 'Minutes']) {
      await expect(component.getByText(unit)).toBeVisible();
    }
    await expect(component.getByText('Seconds')).toHaveCount(0);
  });

  test('stays at zero for an invalid countdown', async ({ mount }) => {
    const component = await mount(storyId, {
      panelOptions: {
        ...timer,
        countdown: 2415621,
        locale: { id: 'en-GB', name: 'English' },
        showTimezone: false
      }
    });

    await expect(
      component.getByText('Ends at: 01/01/1970 2:40 AM')
    ).toBeVisible();
    await expect(component.getByText('00:00:00')).toBeVisible();
  });
});
