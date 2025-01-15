import { Typography } from '@mui/material';

import { DashboardLayout } from '.';

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
  cy.adjustViewport();

  cy.mount({
    Component: (
      <DashboardLayout.Layout layout={dashboardLayout}>
        {dashboardLayout.map(({ i, content }) => (
          <DashboardLayout.Item header={<div>header</div>} id={i} key={i}>
            <Typography>{content}</Typography>
          </DashboardLayout.Item>
        ))}
      </DashboardLayout.Layout>
    )
  });

  cy.viewport('macbook-13');
};

describe('Dashboard', () => {
  it('displays placeholder when panels is out of viewport', () => {
    initialize();

    cy.get('[data-widget-skeleton="a"]').should('not.exist');
    cy.get('[data-widget-skeleton="b"]').should('not.exist');
    cy.get('[data-widget-skeleton="c"]').should('not.exist');

    cy.makeSnapshot();
  });
});
