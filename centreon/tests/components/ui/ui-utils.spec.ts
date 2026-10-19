import { expect, test } from '../fixtures';

/**
 * packages/ui hooks: useLocaleTimezoneDate and useMutationQuery. The 40
 * Cypress date cases each mounted the component; one test checks them all.
 */
test('formats the dates in the locale and timezone asked', async ({
  mount,
  page
}) => {
  await mount('ui/utils/useLocaleTimezoneDate/LocaleTimezoneDate/Api');
  const timezones = [
    'Europe/Paris',
    'Europe/London',
    'UTC',
    'Africa/Casablanca',
    'America/New_York'
  ];
  // L LT then LLLL, for each timezone above.
  const expected: Record<string, Array<string>> = {
    'de-DE': [
      '09.10.2020 16:11',
      'Freitag, 9. Oktober 2020 16:11',
      '09.10.2020 15:11',
      'Freitag, 9. Oktober 2020 15:11',
      '09.10.2020 14:11',
      'Freitag, 9. Oktober 2020 14:11',
      '09.10.2020 15:11',
      'Freitag, 9. Oktober 2020 15:11',
      '09.10.2020 10:11',
      'Freitag, 9. Oktober 2020 10:11'
    ],
    'en-GB': [
      '10/09/2020 4:11 PM',
      'Friday, October 9, 2020 4:11 PM',
      '10/09/2020 3:11 PM',
      'Friday, October 9, 2020 3:11 PM',
      '10/09/2020 2:11 PM',
      'Friday, October 9, 2020 2:11 PM',
      '10/09/2020 3:11 PM',
      'Friday, October 9, 2020 3:11 PM',
      '10/09/2020 10:11 AM',
      'Friday, October 9, 2020 10:11 AM'
    ],
    'fr-FR': [
      '09/10/2020 16:11',
      'vendredi 9 octobre 2020 16:11',
      '09/10/2020 15:11',
      'vendredi 9 octobre 2020 15:11',
      '09/10/2020 14:11',
      'vendredi 9 octobre 2020 14:11',
      '09/10/2020 15:11',
      'vendredi 9 octobre 2020 15:11',
      '09/10/2020 10:11',
      'vendredi 9 octobre 2020 10:11'
    ]
  };
  // Without a locale, the hook formats in English.
  expected.default = expected['en-GB'];

  const formatted = await page.evaluate(
    ({ locales, zones }) =>
      Object.fromEntries(
        locales.map((locale) => [
          locale,
          zones.flatMap((timezone) =>
            ['L LT', 'LLLL'].map(
              (format) =>
                window.formatDate?.({
                  date: 1602252661000,
                  format,
                  locale: locale === 'default' ? undefined : locale,
                  timezone
                }) ?? ''
            )
          )
        ])
      ),
    { locales: Object.keys(expected), zones: timezones }
  );
  expect(formatted).toEqual(expected);
});

test.describe('useMutationQuery', () => {
  const story = 'ui/api/useMutationQuery/useMutationQuery/Default';

  test('posts its payload as JSON', async ({ mockApi, mount, page }) => {
    const endpoint = await mockApi({
      endpoint: './api/latest/endpoint',
      method: 'POST',
      status: 204
    });
    const component = await mount(story);
    const [request] = await Promise.all([
      page.waitForRequest((r) => r.url().endsWith('/endpoint')),
      component.getByRole('button', { name: 'Send' }).click()
    ]);
    expect(request.method()).toBe('POST');
    expect(request.headers()['content-type']).toBe('application/json');
    await expect
      .poll(() => endpoint.bodies)
      .toEqual([{ a: 'a', b: 2, c: ['arr', 'ay'] }]);
  });

  for (const bypassed of [false, true]) {
    test(`${bypassed ? 'hides' : 'shows'} the API error ${bypassed ? 'of a bypassed code' : 'in a snackbar'}`, async ({
      mockApi,
      mount,
      page
    }) => {
      const endpoint = await mockApi({
        endpoint: './api/latest/endpoint',
        method: 'POST',
        response: { message: 'custom error message' },
        status: 400
      });
      const component = await mount(
        story,
        bypassed ? { httpCodesBypassErrorSnackbar: [400] } : {}
      );
      await component.getByRole('button', { name: 'Send' }).click();
      await expect.poll(() => endpoint.bodies.length).toBe(1);
      if (bypassed) {
        await page.waitForTimeout(500);
        await expect(page.getByText('custom error message')).toHaveCount(0);
      } else {
        await expect(page.getByText('custom error message')).toBeVisible();
      }
    });
  }
});
