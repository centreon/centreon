import { expect, test } from './fixtures';

// Props are type-checked through the generated playwright/stories.d.ts registry.
const storyId = 'Inputs/Warning/WithLabel';

test('displays the label given to the field', async ({
  expectThemedScreenshot,
  mount
}) => {
  const label = 'Warning message!';

  const component = await mount(storyId, { label });

  await expect(component.getByText(label)).toBeVisible();

  await expectThemedScreenshot(component, 'widget-warning-field');
});

test('reflects a new label without remounting', async ({ mount }) => {
  const component = await mount(storyId, { label: 'First' });

  await expect(component.getByText('First')).toBeVisible();

  await component.update({ label: 'Second' });

  await expect(component.getByText('Second')).toBeVisible();
});
