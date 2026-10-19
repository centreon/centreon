import {
  labelCancel,
  labelName,
  labelNewFilter,
  labelSave
} from '../../www/front_src/src/Resources/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Resources/Filter/Save/CreateFilterDialog.
const storyId = 'Save/CreateFilterDialog/Default';

test('starts with an empty name and a disabled save button', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  await mount(storyId);
  const dialog = page.getByRole('dialog');

  await expect(dialog.getByText(labelNewFilter)).toBeVisible();
  await expect(dialog.getByRole('textbox', { name: labelName })).toHaveValue(
    ''
  );
  await expect(dialog.getByRole('button', { name: labelCancel })).toBeEnabled();
  await expect(dialog.getByRole('button', { name: labelSave })).toBeDisabled();

  await expectThemedScreenshot(dialog, 'create-filter-dialog');
});

test('keeps an existing name read-only', async ({ mount, page }) => {
  await mount(storyId, { name: 'Filter name' });
  const name = page
    .getByRole('dialog')
    .getByRole('textbox', { name: labelName });

  await expect(name).toHaveValue('Filter name');
  await expect(name).toBeDisabled();
  await expect(
    page.getByRole('dialog').getByRole('button', { name: labelSave })
  ).toBeEnabled();
});

test('takes a typed name', async ({ mount, page }) => {
  await mount(storyId, { name: '' });
  const name = page
    .getByRole('dialog')
    .getByRole('textbox', { name: labelName });

  await name.fill('New filter');

  await expect(name).toHaveValue('New filter');
});

test('calls the cancel callback', async ({ mount, page }) => {
  const component = await mount(storyId, { name: 'Filter name' });

  await page
    .getByRole('dialog')
    .getByRole('button', { name: labelCancel })
    .click();

  await expect(component.getByTestId('cancel-count')).toHaveValue('1');
});

const saveOutcomes = [
  {
    expected: '1',
    name: 'calls the success callback once saved',
    reject: false
  },
  { expected: '0', name: 'does not call it when the save fails', reject: true }
];

for (const { expected, name, reject } of saveOutcomes) {
  test(name, async ({ mount, page }) => {
    const component = await mount(storyId, { name: 'Filter name', reject });

    await page
      .getByRole('dialog')
      .getByRole('button', { name: labelSave })
      .click();

    await expect(component.getByTestId('success-count')).toHaveValue(expected);
  });
}
