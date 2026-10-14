import {
  labelMinute,
  labelMinutes
} from '../../www/front_src/src/Authentication/Local/translatedLabels';
import { expect, test } from './fixtures';

const storyId = 'TimeInputs/TimeInput/Minutes';

test('converts the selected minutes into milliseconds', async ({
  mount,
  page
}) => {
  const component = await mount(storyId);

  await component
    .getByRole('combobox', { name: `input ${labelMinute}` })
    .click();
  // The options are rendered in a portalled listbox.
  await page.getByRole('option', { exact: true, name: '34' }).click();

  await expect(component.getByTestId('last-change')).toHaveValue('2040000');
});

test('hides the options below the minimum, except 0', async ({
  mount,
  page
}) => {
  const component = await mount(storyId, { minOption: 2 });

  await component
    .getByRole('combobox', { name: `input ${labelMinute}` })
    .click();
  const listbox = page.getByRole('listbox');

  await expect(
    listbox.getByRole('option', { exact: true, name: '0' })
  ).toBeVisible();
  await expect(
    listbox.getByRole('option', { exact: true, name: '1' })
  ).toHaveCount(0);
  await expect(
    listbox.getByRole('option', { exact: true, name: '2' })
  ).toBeVisible();
});

const unitLabels = [
  { expected: labelMinute, minutes: 0 },
  { expected: labelMinute, minutes: 1 },
  { expected: labelMinutes, minutes: 2 }
];

for (const { expected, minutes } of unitLabels) {
  test(`labels ${minutes} minute(s) as "${expected}"`, async ({ mount }) => {
    const component = await mount(storyId, { timeValue: minutes * 60_000 });

    await expect(component.getByText(expected, { exact: true })).toBeVisible();
  });
}
