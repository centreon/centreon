import { expect, test } from './fixtures';

// Migrated from AuthenticationTokens/Filters/DateInput/DateInput.
const storyId = 'DateInput/DateInput/Default';

test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date('2025-08-21T10:00:00.000Z'));
});

const typeDate = async (
  input: import('@playwright/test').Locator,
  value: string
): Promise<void> => {
  await input.clear();
  await input.pressSequentially(value);
};

test('renders the default date picker', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(
    component.getByTestId('test-date-calendarContainer')
  ).toBeVisible();
  await expect(component.getByText('Until')).toBeVisible();
  await expect(component.getByTestId('calendarInput')).toBeVisible();
  await expect(component.getByTestId('current-date')).toHaveText('No date set');

  await expectThemedScreenshot(component, 'token-date-input');
});

test('names its container after the label', async ({ mount }) => {
  const component = await mount(storyId, { label: 'custom-date-picker' });

  await expect(
    component.getByTestId('custom-date-picker-calendarContainer')
  ).toBeVisible();
});

const initialDates = [
  { expected: '25/08/2025 14:30', iso: '2025-08-25T14:30:00+02:00' },
  { expected: '25/12/2025 15:45', iso: '2025-12-25T15:45:30+01:00' }
];

for (const { expected, iso } of initialDates) {
  test(`displays the initial date ${expected}`, async ({ mount }) => {
    const component = await mount(storyId, { initialDate: iso });

    await expect(component.getByTestId('calendarInput')).toHaveValue(expected);
  });
}

test('keeps a typed date uncommitted until Enter', async ({ mount }) => {
  const component = await mount(storyId);
  const input = component.getByTestId('calendarInput');

  await typeDate(input, '25/12/2025 15:45');
  await expect(input).toHaveValue('25/12/2025 15:45');
  await expect(component.getByTestId('current-date')).toHaveText('No date set');

  await input.press('Enter');

  await expect(component.getByTestId('current-date')).toHaveText(
    '2025-12-25 15:45'
  );
  await expect(component.getByTestId('calendar-display')).toHaveText(
    'Calendar hidden'
  );
});

test('commits the time of the typed date', async ({ mount }) => {
  const component = await mount(storyId);
  const input = component.getByTestId('calendarInput');

  await typeDate(input, '21/08/2025 23:30');
  await input.press('Enter');

  await expect(component.getByTestId('current-date')).toHaveText(
    '2025-08-21 23:30'
  );
});

test('flags an invalid date until a valid one is typed', async ({ mount }) => {
  const component = await mount(storyId);
  const input = component.getByTestId('calendarInput');

  await typeDate(input, 'invalid-date');
  await input.blur();
  await expect(component.getByText('invalid date')).toBeVisible();

  await typeDate(input, '25/12/2025 15:45');
  await input.blur();
  await expect(component.getByText('invalid date')).toHaveCount(0);
});

test('does not commit an invalid date on Enter', async ({ mount }) => {
  const component = await mount(storyId, {
    initialDate: '2025-08-25T14:30:00+02:00'
  });
  const input = component.getByTestId('calendarInput');

  await typeDate(input, 'invalid-date');
  await input.press('Enter');

  await expect(component.getByText('invalid date')).toBeVisible();
  await expect(component.getByTestId('current-date')).toHaveText(
    '2025-08-25 14:30'
  );
  await expect(component.getByTestId('calendar-display')).toHaveText(
    'Calendar shown'
  );
});

test('focuses the input when clicked', async ({ mount }) => {
  const component = await mount(storyId);
  const input = component.getByTestId('calendarInput');

  await input.click();

  await expect(input).toBeFocused();
});
