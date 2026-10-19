import { expect, test } from '../fixtures';

/**
 * packages/ui DateTimePickerInput: walking through 2023 month by month, the
 * calendar shows each month with its weeks, weekdays and header. The Cypress
 * spec hardcoded the months; they are computed here.
 */
const story = 'ui/TimePeriods/DateTimePickerInput/Default';
const monthNames = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December'
];

test.use({ viewport: { height: 800, width: 1280 } });

const monthOf2023 = (month: number) => {
  const firstWeekday = new Date(Date.UTC(2023, month, 1)).getUTCDay();
  const days = new Date(Date.UTC(2023, month + 1, 0)).getUTCDate();
  const lastWeekday = new Date(Date.UTC(2023, month, days)).getUTCDay();

  return {
    days,
    firstWeekday,
    lastWeekday,
    weeks: Math.ceil((firstWeekday + days) / 7)
  };
};

for (const [button, start, months] of [
  ['Next month', '2023-01-01T12:59:41.041Z', [...monthNames.keys()]],
  [
    'Previous month',
    '2023-12-01T12:59:41.041Z',
    [...monthNames.keys()].reverse()
  ]
] as const) {
  test(`walks through 2023 with the ${button.toLowerCase()} button`, async ({
    expectThemedScreenshot,
    mount,
    page
  }) => {
    const component = await mount(story, {
      date: start,
      timezone: 'Europe/Paris'
    });
    await component.getByRole('button').first().click();
    const weeks = page
      .locator('[role="rowgroup"]')
      .first()
      .locator(':scope > *');

    for (const [step, month] of months.entries()) {
      const {
        days,
        firstWeekday,
        lastWeekday,
        weeks: weekCount
      } = monthOf2023(month);
      await expect(page.getByText(`${monthNames[month]} 2023`)).toBeVisible();
      for (const weekday of ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']) {
        await expect(
          page.getByText(weekday, { exact: true }).first()
        ).toBeVisible();
      }
      await expect(weeks).toHaveCount(weekCount);
      const dayButtons = weeks.locator(':scope > button');
      await expect(dayButtons).toHaveCount(days);
      const labels = await dayButtons.allTextContents();
      expect(new Set(labels).size).toBe(days);
      await expect(weeks.first().locator(':scope > *')).toHaveCount(7);
      await expect(
        weeks.first().locator(':scope > *').nth(firstWeekday)
      ).toHaveText('1');
      await expect(
        weeks.last().locator(':scope > *').nth(lastWeekday)
      ).toHaveText(String(days));

      if (step === 0) {
        await expectThemedScreenshot(
          page,
          `date-time-picker-${monthNames[month].toLowerCase()}`
        );
      }
      if (step < months.length - 1) {
        await page.getByLabel(button, { exact: true }).click();
      }
    }
  });
}
