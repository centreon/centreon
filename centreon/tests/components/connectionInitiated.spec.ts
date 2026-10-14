import type { Locator } from '@playwright/test';

import { expect, test } from './fixtures';

// Migrated from AgentConfiguration/Form/ConnectionInitiated/ConnectionInitiated.
const storyId = 'ConnectionInitiated/ConnectionInitiated/Default';
const publicCertificate = 'Public certificate (.crt, .cert, .cer)';

const panel = (component: Locator, id: 'agent' | 'poller'): Locator =>
  component.locator(`[role="tabpanel"][id*="${id}"]`);
const tab = (component: Locator, name: 'By agent' | 'By poller'): Locator =>
  component.getByRole('tab', { name });
const enabled = (
  component: Locator,
  id: 'agent' | 'poller' = 'agent'
): Locator => panel(component, id).getByRole('checkbox', { name: 'Enable' });

test('opens on the agent tab, disabled', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  await expect(tab(component, 'By agent')).toBeVisible();
  await expect(tab(component, 'By poller')).toBeVisible();
  await expect(panel(component, 'agent')).toBeVisible();
  await expect(panel(component, 'poller')).toBeHidden();
  await expect(enabled(component)).not.toBeChecked();
  await expect(component.getByText('Enable').first()).toBeVisible();
  await expect(component.getByTestId('InfoOutlinedIcon')).toHaveCount(2);

  await expectThemedScreenshot(component, 'connection-initiated');
});

test('switches between the tabs', async ({ mount }) => {
  const component = await mount(storyId);

  await tab(component, 'By poller').click();
  await expect(panel(component, 'poller')).toBeVisible();
  await expect(panel(component, 'agent')).toBeHidden();

  await tab(component, 'By agent').click();
  await expect(panel(component, 'agent')).toBeVisible();
});

const selectedIcons = [
  { name: 'By agent' as const, props: { agentInitiated: true } },
  { name: 'By poller' as const, props: { pollerInitiated: true } }
];

for (const { name, props } of selectedIcons) {
  test(`marks "${name}" as selected when enabled`, async ({ mount }) => {
    const component = await mount(storyId, props);

    await expect(component.getByTestId(`${name} selected`)).toBeVisible();
  });
}

test('toggles the agent initiated fields', async ({ mount }) => {
  const component = await mount(storyId);

  await enabled(component).click();

  await expect(enabled(component)).toBeChecked();
  for (const field of [
    publicCertificate,
    'CA (.crt, .cert, .cer)',
    'Private key (.key)',
    'Select existing CMA token(s)'
  ]) {
    await expect(component.getByTestId(field).first()).toBeVisible();
  }

  await enabled(component).click();

  await expect(enabled(component)).not.toBeChecked();
  await expect(component.getByTestId(publicCertificate)).toHaveCount(0);
});

test('toggles the poller initiated hosts', async ({ mount }) => {
  const component = await mount(storyId);

  await tab(component, 'By poller').click();
  await enabled(component, 'poller').click();

  await expect(enabled(component, 'poller')).toBeChecked();
  await expect(panel(component, 'poller')).toContainText('Monitored hosts');
  await expect(panel(component, 'poller')).toContainText('Select host');

  await enabled(component, 'poller').click();

  await expect(enabled(component, 'poller')).not.toBeChecked();
});

const modes = [
  { mode: 'no-tls', visible: false },
  { mode: 'secure', visible: true }
];

for (const { mode, visible } of modes) {
  test(`${visible ? 'shows' : 'hides'} the certificates in ${mode} mode`, async ({
    mount
  }) => {
    const component = await mount(storyId, {
      agentInitiated: true,
      connectionMode: mode
    });

    const certificate = component.getByTestId(publicCertificate);
    if (visible) {
      await expect(certificate.first()).toBeVisible();
    } else {
      await expect(certificate).toHaveCount(0);
    }
  });
}

test('keeps the agent configuration across tab switches', async ({ mount }) => {
  const component = await mount(storyId);

  await enabled(component).click();
  await tab(component, 'By poller').click();
  await tab(component, 'By agent').click();

  await expect(enabled(component)).toBeChecked();
  await expect(component.getByTestId(publicCertificate).first()).toBeVisible();
});
