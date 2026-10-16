import { expect, test } from './fixtures';

// Migrated from Dashboards/.../WidgetProperties/Inputs/Autocomplete/ConnectedAutocomplete.
const storyId = 'Autocomplete/ConnectedAutocomplete/Default';
const options = { test: undefined };

test.beforeEach(async ({ mockApi }) => {
  await mockApi({
    endpoint: './api/latest/endpoint',
    response: {
      meta: { limit: 2, page: 1, total: 2 },
      result: [
        { id: 0, name: 'My Option 1' },
        { id: 1, name: 'My Option 2' }
      ]
    }
  });
});

test('selects a value in single mode', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(storyId, {
    inputProps: { isSingleAutocomplete: true },
    options
  });
  const field = component.getByLabel('Label');

  await field.click();
  await page.getByRole('option', { name: 'My Option 2' }).click();

  await expect(field).toHaveValue('My Option 2');
  await expectThemedScreenshot(component, 'connected-autocomplete-single');
});

test('selects then removes values in multi mode', async ({ mount, page }) => {
  const component = await mount(storyId, {
    inputProps: { isSingleAutocomplete: false },
    options
  });

  await component.getByLabel('Label').click();
  await page.getByRole('option', { name: 'My Option 1' }).click();
  await page.getByRole('option', { name: 'My Option 2' }).click();
  await component.getByText('Autocomplete').click();

  const chips = component.getByRole('button').filter({ hasText: 'My Option' });
  await expect(chips).toHaveText(['My Option 1', 'My Option 2']);

  await chips
    .filter({ hasText: 'My Option 2' })
    .getByTestId('CancelIcon')
    .click();

  await expect(chips).toHaveText(['My Option 1']);
});

test('is disabled without edition rights', async ({ mount }) => {
  const component = await mount(storyId, { canEdit: false, options });

  await expect(component.getByLabel('Label')).toBeDisabled();
});

test('displays its title in a group', async ({ mount }) => {
  const component = await mount(storyId, {
    inputProps: { isInGroup: true },
    options
  });

  await expect(component.getByText('Autocomplete')).toBeVisible();
});
