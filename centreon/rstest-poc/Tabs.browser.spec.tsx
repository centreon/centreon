import { Typography } from '@mui/material';

import { describe, expect, it } from '@rstest/core';

import { Tabs } from '../packages/ui/src/components/Tabs';
import { TabPanel } from '../packages/ui/src/components/Tabs/TabPanel';
import { fireEvent, render, screen } from './testRender';

/**
 * Rstest Browser Mode port of packages/ui/src/components/Tabs/Tabs.cypress.spec.tsx
 * (runs in a real Chromium via Playwright). The cy.makeSnapshot() visual check
 * is intentionally dropped — visual regression is a separate concern.
 */
const renderTabs = (withTabListProps = false): HTMLElement => {
  const { baseElement } = render(
    <Tabs
      defaultTab="tab 0"
      tabList={withTabListProps ? { variant: 'fullWidth' } : undefined}
      tabs={[
        { label: 'Tab 0', value: 'tab 0' },
        { label: 'Tab 1', value: 'tab 1' }
      ]}
    >
      <TabPanel value="tab 0">
        <Typography>Tab 0</Typography>
      </TabPanel>
      <TabPanel value="tab 1">
        <Typography>Tab 1</Typography>
      </TabPanel>
    </Tabs>
  );

  return baseElement;
};

describe('Tabs (Rstest Browser Mode POC)', () => {
  it('shows the selected tab content and switches on click', () => {
    const baseElement = renderTabs();

    expect(
      baseElement.querySelector('[data-TabPanel="tab 0"]')
    ).not.toHaveAttribute('hidden');
    expect(
      baseElement.querySelector('[data-TabPanel="tab 1"]')
    ).toHaveAttribute('hidden');
    expect(screen.getByLabelText('Tab 0')).toHaveAttribute(
      'aria-selected',
      'true'
    );
    expect(screen.getByLabelText('Tab 1')).toHaveAttribute(
      'aria-selected',
      'false'
    );

    fireEvent.click(screen.getByText('Tab 1'));

    expect(
      baseElement.querySelector('[data-TabPanel="tab 0"]')
    ).toHaveAttribute('hidden');
    expect(
      baseElement.querySelector('[data-TabPanel="tab 1"]')
    ).not.toHaveAttribute('hidden');
    expect(screen.getByLabelText('Tab 0')).toHaveAttribute(
      'aria-selected',
      'false'
    );
    expect(screen.getByLabelText('Tab 1')).toHaveAttribute(
      'aria-selected',
      'true'
    );
  });

  it('renders with tabList props set', () => {
    const baseElement = renderTabs(true);

    expect(
      baseElement.querySelector('[data-TabPanel="tab 0"]')
    ).not.toHaveAttribute('hidden');
    expect(
      baseElement.querySelector('[data-TabPanel="tab 1"]')
    ).toHaveAttribute('hidden');
  });
});
