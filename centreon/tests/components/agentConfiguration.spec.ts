import { expect, test } from './fixtures';

// Migrated from AgentConfiguration/Form/ConnectionInitiated/HostConfigurations/AddButton.
const storyId = 'HostConfigurations/AddButton/Default';

test('adds a host when the button is clicked', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);
  const button = component.getByRole('button', { name: 'Add a host' });

  await expect(button).toBeVisible();
  await expect(button).toHaveText('Add a host');
  await expect(button.getByTestId('AddCircleIcon')).toBeVisible();
  await expectThemedScreenshot(button, 'add-host-button');

  await button.click();

  await expect(component.getByTestId('add-count')).toHaveValue('1');
});

test('does not add a host when disabled', async ({ mount }) => {
  const component = await mount(storyId, { addButtonDisabled: true });
  const button = component.getByRole('button', { name: 'Add a host' });

  await expect(button).toBeDisabled();
  await button.click({ force: true });

  await expect(component.getByTestId('add-count')).toHaveValue('0');
});
