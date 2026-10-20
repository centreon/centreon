import { Typography } from '@mui/material';

import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { Tabs } from '.';
import { TabPanel } from './TabPanel';

const initialize = (withTabListProps = false): void => {
  render(
    <Tabs
      defaultTab="tab 0"
      tabList={
        withTabListProps
          ? {
              variant: 'fullWidth'
            }
          : undefined
      }
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
};

const getTabPanel = (value: string): HTMLElement =>
  document.querySelector(`[data-TabPanel="${value}"]`) as HTMLElement;

const getFontWeight = (element: HTMLElement): string =>
  getComputedStyle(element).fontWeight;

describe('Tabs', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays tabs and their content when a tab is selected', async () => {
    initialize();

    await waitFor(() => {
      expect(getTabPanel('tab 0')).not.toHaveAttribute('hidden');
    });
    expect(getTabPanel('tab 1')).toHaveAttribute('hidden');
    expect(screen.getByLabelText('Tab 0')).toHaveAttribute(
      'aria-selected',
      'true'
    );
    expect(screen.getByLabelText('Tab 1')).toHaveAttribute(
      'aria-selected',
      'false'
    );

    await userEvent.click(screen.getByRole('tab', { name: 'Tab 1' }));

    await waitFor(() => {
      expect(getTabPanel('tab 0')).toHaveAttribute('hidden');
    });
    expect(getTabPanel('tab 1')).not.toHaveAttribute('hidden');
    expect(screen.getByLabelText('Tab 0')).toHaveAttribute(
      'aria-selected',
      'false'
    );
    expect(screen.getByLabelText('Tab 1')).toHaveAttribute(
      'aria-selected',
      'true'
    );

    // Replaces the visual snapshot: the selected tab in bold, the other one
    // not, and the content of the selected panel only.
    expect(getFontWeight(screen.getByLabelText('Tab 1'))).toBe('700');
    expect(getFontWeight(screen.getByLabelText('Tab 0'))).toBe('400');
    expect(getTabPanel('tab 1')).toHaveTextContent(/^Tab 1$/);
    expect(getTabPanel('tab 1')).toBeVisible();
    expect(getTabPanel('tab 0')).not.toBeVisible();
  });

  it('displays tabs when tabList props are set', async () => {
    initialize(true);

    await waitFor(() => {
      expect(getTabPanel('tab 0')).not.toHaveAttribute('hidden');
    });
    expect(getTabPanel('tab 1')).toHaveAttribute('hidden');

    // Replaces the visual snapshot: the first tab selected, in bold, and the
    // two tabs sharing the full width of the page.
    const firstTab = screen.getByLabelText('Tab 0');
    const secondTab = screen.getByLabelText('Tab 1');

    expect(firstTab).toHaveAttribute('aria-selected', 'true');
    expect(getFontWeight(firstTab)).toBe('700');
    expect(getFontWeight(secondTab)).toBe('400');
    expect(getTabPanel('tab 0')).toHaveTextContent(/^Tab 0$/);

    const tabList = screen.getByRole('tablist');
    const tabListWidth = tabList.getBoundingClientRect().width;
    const firstTabWidth = firstTab.getBoundingClientRect().width;

    expect(firstTabWidth).toBeGreaterThan(tabListWidth / 2 - 32);
    expect(secondTab.getBoundingClientRect().width).toBeCloseTo(
      firstTabWidth,
      0
    );
  });
});
