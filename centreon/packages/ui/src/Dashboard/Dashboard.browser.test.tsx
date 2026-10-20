import { Typography } from '@mui/material';

import { render } from '@test-setup/render';
import { screen, waitFor } from '@testing-library/react';
import type { LayoutItem } from 'react-grid-layout';

import { DashboardLayout } from '.';

interface CustomLayout extends LayoutItem {
  content: string;
  shouldUseFluidTypography?: boolean;
}

const dashboardLayout: Array<CustomLayout> = [
  {
    content: 'Panel 1',
    h: 4,
    i: 'a',
    shouldUseFluidTypography: false,
    w: 6,
    x: 0,
    y: 0
  },
  {
    content: 'Panel 2',
    h: 3,
    i: 'b',
    minW: 2,
    w: 7,
    x: 1,
    y: 7
  },
  {
    content: 'Panel 3',
    h: 7,
    i: 'c',
    w: 6,
    x: 6,
    y: 6
  },
  {
    content: 'Panel 4',
    h: 3,
    i: 'd',
    minW: 2,
    w: 5,
    x: 4,
    y: 10
  }
];

const initialize = (): void => {
  render(
    <DashboardLayout.Layout layout={dashboardLayout}>
      {dashboardLayout.map(({ i, content }) => (
        <DashboardLayout.Item header={<div>header</div>} id={i} key={i}>
          {[<Typography key="content">{content}</Typography>]}
        </DashboardLayout.Item>
      ))}
    </DashboardLayout.Layout>
  );
};

const panelBox = (content: string): DOMRect =>
  (
    screen.getByText(content).closest('.react-grid-item') as HTMLElement
  ).getBoundingClientRect();

describe('Dashboard', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('displays placeholder when panels is out of viewport', async () => {
    initialize();

    // As in Cypress: no component renders this attribute any more, so these
    // three checks pass whatever the layout does.
    expect(
      document.querySelector('[data-widget-skeleton="a"]')
    ).not.toBeInTheDocument();
    expect(
      document.querySelector('[data-widget-skeleton="b"]')
    ).not.toBeInTheDocument();
    expect(
      document.querySelector('[data-widget-skeleton="c"]')
    ).not.toBeInTheDocument();

    // Replaces the snapshot: every panel renders its header and content,
    // Panels 1 and 3 side by side on the first row, Panel 2 compacted right
    // under Panel 3, and Panel 4 under Panel 2.
    expect(await screen.findByText('Panel 1')).toBeVisible();
    expect(screen.getByText('Panel 2')).toBeVisible();
    expect(screen.getByText('Panel 3')).toBeVisible();
    expect(screen.getByText('Panel 4')).toBeInTheDocument();
    expect(screen.getAllByText('header')).toHaveLength(4);

    await waitFor(() => {
      const panel1 = panelBox('Panel 1');
      const panel3 = panelBox('Panel 3');

      expect(panel1.width).toBeGreaterThan(0);
      expect(panel3.top).toBe(panel1.top);
      expect(panel3.left).toBeGreaterThan(panel1.right);
      expect(panel3.width).toBe(panel1.width);
      expect(panel3.height).toBeGreaterThan(panel1.height);
    });

    const panel1 = panelBox('Panel 1');
    const panel2 = panelBox('Panel 2');
    const panel3 = panelBox('Panel 3');
    const panel4 = panelBox('Panel 4');

    expect(panel2.top).toBeGreaterThan(panel3.bottom);
    expect(panel2.left).toBeGreaterThan(panel1.left);
    expect(panel2.left).toBeLessThan(panel1.right);
    expect(panel4.top).toBeGreaterThan(panel2.bottom);
  });
});
