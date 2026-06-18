import { expect, test } from '@playwright/experimental-ct-react';

import type { HooksConfig } from '../../../../../../../playwright/index';
import AddButton from './AddButton';

/**
 * Playwright Component Testing migration of `AddButton.cypress.spec.tsx`,
 * including a visual snapshot (the equivalent of the Cypress `cy.makeSnapshot()`
 * step) captured in both the light and the dark MUI themes. The theme is
 * injected by the `beforeMount` hook via `hooksConfig`.
 */

test('renders the add button with its label and icon', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <AddButton addButtonDisabled={false} onAddItem={() => {}} />
  );

  const button = component.getByTestId('Add a host');
  await expect(button).toBeVisible();
  await expect(button).toContainText('Add a host');
  await expect(button).toHaveAttribute('aria-label', 'Add a host');
  // The MUI icon's data-testid is only emitted in development builds, so assert
  // the rendered SVG instead.
  await expect(button.locator('svg')).toBeVisible();
});

test('disables the button when addButtonDisabled is true', async ({
  mount
}) => {
  const component = await mount<HooksConfig>(
    <AddButton addButtonDisabled onAddItem={() => {}} />
  );

  await expect(component.getByTestId('Add a host')).toBeDisabled();
});

test('matches the visual snapshot (light theme)', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <AddButton addButtonDisabled={false} onAddItem={() => {}} />,
    { hooksConfig: { theme: 'light' } }
  );

  await expect(component).toHaveScreenshot('add-button-light.png');
});

test('matches the visual snapshot (dark theme)', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <AddButton addButtonDisabled={false} onAddItem={() => {}} />,
    { hooksConfig: { theme: 'dark' } }
  );

  await expect(component).toHaveScreenshot('add-button-dark.png');
});
