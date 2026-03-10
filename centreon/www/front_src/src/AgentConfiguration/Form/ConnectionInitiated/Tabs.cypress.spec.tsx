import { Tabs } from './Tabs';

const defaultTabs = [
  { label: 'Tab 1', value: 'tab1' },
  { label: 'Tab 2', value: 'tab2' },
  { label: 'Tab 3', value: 'tab3' }
];

const defaultChildren = [
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

const defaultProps = {
  children: defaultChildren,
  defaultTab: 'tab1',
  tabs: defaultTabs
};

const initialize = (values = {}): void => {
  cy.mount({
    Component: <Tabs {...defaultProps} {...values} />
  });
};

describe('Tabs', () => {
  it('renders all tabs correctly', () => {
    initialize();

    cy.get('[role="tab"]').should('have.length', 3);
    cy.get('[role="tab"]').eq(0).should('contain.text', 'Tab 1');
    cy.get('[role="tab"]').eq(1).should('contain.text', 'Tab 2');
    cy.get('[role="tab"]').eq(2).should('contain.text', 'Tab 3');

    cy.makeSnapshot();
  });

  it('displays the default tab as selected', () => {
    initialize();

    cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-selected', 'true');
    cy.get('[role="tab"]').eq(1).should('have.attr', 'aria-selected', 'false');
    cy.get('[role="tab"]').eq(2).should('have.attr', 'aria-selected', 'false');
  });

  it('changes tab when clicked', () => {
    initialize();

    cy.get('[role="tab"]').eq(1).click();
    cy.get('[role="tab"]').eq(1).should('have.attr', 'aria-selected', 'true');
    cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-selected', 'false');
  });

  it('renders with different default tab', () => {
    initialize({ defaultTab: 'tab2' });

    cy.get('[role="tab"]').eq(1).should('have.attr', 'aria-selected', 'true');
    cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-selected', 'false');
  });

  it('applies correct aria-label to tabs', () => {
    initialize();

    cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-label', 'Tab 1');
    cy.get('[role="tab"]').eq(1).should('have.attr', 'aria-label', 'Tab 2');
    cy.get('[role="tab"]').eq(2).should('have.attr', 'aria-label', 'Tab 3');
  });

  it('renders with JSX element labels', () => {
    const tabsWithJSXLabels = [
      { label: <span>Custom Tab 1</span>, value: 'custom1' },
      { label: <span>Custom Tab 2</span>, value: 'custom2' }
    ];

    initialize({ tabs: tabsWithJSXLabels });

    cy.get('[role="tab"]').should('have.length', 2);
    cy.get('[role="tab"]').eq(0).should('contain', 'Custom Tab 1');
    cy.get('[role="tab"]').eq(1).should('contain', 'Custom Tab 2');
  });

  it('passes through additional tabList props', () => {
    const tabListProps = {
      'data-testid': 'custom-tabs'
    };

    initialize({ tabList: tabListProps });

    cy.get('[data-testid="custom-tabs"]').should('exist');
  });

  it('maintains tab state across multiple clicks', () => {
    initialize();

    cy.get('[role="tab"]').eq(2).click();
    cy.get('[role="tab"]').eq(2).should('have.attr', 'aria-selected', 'true');

    cy.get('[role="tab"]').eq(0).click();
    cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-selected', 'true');
    cy.get('[role="tab"]').eq(2).should('have.attr', 'aria-selected', 'false');
  });

  it('renders children within TabContext', () => {
    initialize();

    cy.get('[data-testid="tab1-content"]').should('exist');
    cy.get('[data-testid="tab2-content"]').should('exist');
    cy.get('[data-testid="tab3-content"]').should('exist');
  });
});
