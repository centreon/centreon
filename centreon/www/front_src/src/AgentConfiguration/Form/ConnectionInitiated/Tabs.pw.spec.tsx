import { expect, test } from '@playwright/experimental-ct-react';

import type { HooksConfig } from '../../../../../../playwright/index';
import { Tabs } from './Tabs';

/**
 * Playwright Component Testing migration of `Tabs.cypress.spec.tsx`: renders the
 * tabs, exercises tab switching (DOM-observable via `aria-selected`) and
 * captures a visual snapshot (the Cypress `cy.makeSnapshot()` equivalent) in the
 * light and dark themes.
 */

const tabs = [
  { ariaLabel: 'Tab 1', label: 'Tab 1', value: 'tab1' },
  { ariaLabel: 'Tab 2', label: 'Tab 2', value: 'tab2' },
  { ariaLabel: 'Tab 3', label: 'Tab 3', value: 'tab3' }
];

const children = [
  <div data-testid="tab1-content" key="tab1">
    Tab 1 Content
  </div>,
  <div data-testid="tab2-content" key="tab2">
    Tab 2 Content
  </div>,
  <div data-testid="tab3-content" key="tab3">
    Tab 3 Content
  </div>
];

test('renders all tabs with the default one selected', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <Tabs defaultTab="tab1" tabs={tabs}>
      {children}
    </Tabs>
  );

  const tabElements = component.getByRole('tab');
  await expect(tabElements).toHaveCount(3);
  await expect(tabElements.nth(0)).toHaveAttribute('aria-selected', 'true');
  await expect(tabElements.nth(1)).toHaveAttribute('aria-selected', 'false');
});

test('selects a tab when it is clicked', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <Tabs defaultTab="tab1" tabs={tabs}>
      {children}
    </Tabs>
  );

  const tabElements = component.getByRole('tab');
  await tabElements.nth(1).click();

  await expect(tabElements.nth(1)).toHaveAttribute('aria-selected', 'true');
  await expect(tabElements.nth(0)).toHaveAttribute('aria-selected', 'false');
});

test('matches the visual snapshot (light theme)', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <Tabs defaultTab="tab1" tabs={tabs}>
      {children}
    </Tabs>,
    { hooksConfig: { theme: 'light' } }
  );

  await expect(component).toHaveScreenshot('tabs-light.png');
});

test('matches the visual snapshot (dark theme)', async ({ mount }) => {
  const component = await mount<HooksConfig>(
    <Tabs defaultTab="tab1" tabs={tabs}>
      {children}
    </Tabs>,
    { hooksConfig: { theme: 'dark' } }
  );

  await expect(component).toHaveScreenshot('tabs-dark.png');
});
