import { Typography } from '@mui/material';

import { TabPanel } from './TabPanel';

import { Tabs } from '.';

const initialize = (withTabListProps = false): void => {
  cy.mount({
    Component: (
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
    )
  });
};

describe('Tabs', () => {
  it('displays tabs and their content when a tab is selected', () => {
    initialize();

    cy.get('[data-TabPanel="tab 0"]').should('not.have.attr', 'hidden');
    cy.get('[data-TabPanel="tab 1"]').should('have.attr', 'hidden');
    cy.findByLabelText('Tab 0')
      .should('have.attr', 'aria-selected')
      .and('equals', 'true');
    cy.findByLabelText('Tab 1')
      .should('have.attr', 'aria-selected')
      .and('equals', 'false');

    cy.contains('Tab 1').click();

    cy.get('[data-TabPanel="tab 0"]').should('have.attr', 'hidden');
    cy.get('[data-TabPanel="tab 1"]').should('not.have.attr', 'hidden');
    cy.findByLabelText('Tab 0')
      .should('have.attr', 'aria-selected')
      .and('equals', 'false');
    cy.findByLabelText('Tab 1')
      .should('have.attr', 'aria-selected')
      .and('equals', 'true');

    cy.makeSnapshot();
  });

  it('displays tabs when tabList props are set', () => {
    initialize(true);

    cy.get('[data-TabPanel="tab 0"]').should('not.have.attr', 'hidden');
    cy.get('[data-TabPanel="tab 1"]').should('have.attr', 'hidden');

    cy.makeSnapshot();
  });
});
