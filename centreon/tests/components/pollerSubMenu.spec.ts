import {
  labelBeta,
  labelCreateNewPoller
} from '../../www/front_src/src/Header/Poller/translatedLabels';
import { expect, test } from './fixtures';

// Migrated from Header/Poller/PollerSubMenu/PollerSubMenu.
const storyId = 'PollerSubMenu/PollerSubMenu/Default';
const beta = labelBeta.toLocaleUpperCase();

test('offers to create a poller on-premise', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(component.getByTestId(labelCreateNewPoller)).toBeVisible();
  await expect(component.getByText(beta)).toBeVisible();

  await expectThemedScreenshot(component, 'poller-sub-menu');
});

test('opens the creation modal without central address on cloud', async ({
  mount,
  page
}) => {
  const component = await mount(storyId, { isCloudPlatform: true });

  await component.getByTestId(labelCreateNewPoller).click();

  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByTestId('centreon-central-address')).toHaveCount(0);
});

const hidden = [
  {
    name: 'while the platform features load',
    props: { isCloudPlatform: null }
  },
  { name: 'without the poller permission', props: { canCreatePoller: false } }
];

for (const { name, props } of hidden) {
  test(`hides the creation button ${name}`, async ({ mount }) => {
    const component = await mount(storyId, props);

    await expect(component.getByTestId(labelCreateNewPoller)).toHaveCount(0);
  });
}

test('does not open the modal from the beta chip', async ({ mount, page }) => {
  const component = await mount(storyId);

  await component.getByText(beta).click();

  await expect(page.getByRole('dialog')).toHaveCount(0);
});
