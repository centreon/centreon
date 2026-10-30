import type { TabsProps } from '@mui/material';

import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { JSX } from 'react';

import { Tabs } from './Tabs';

interface TabDefinition {
  ariaLabel: string;
  label: string | JSX.Element;
  value: string;
}

interface InitializeProps {
  defaultTab?: string;
  tabList?: TabsProps;
  tabs?: Array<TabDefinition>;
}

const defaultTabs = [
  { ariaLabel: 'Tab 1', label: 'Tab 1', value: 'tab1' },
  { ariaLabel: 'Tab 2', label: 'Tab 2', value: 'tab2' },
  { ariaLabel: 'Tab 3', label: 'Tab 3', value: 'tab3' }
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

const initialize = ({
  defaultTab = 'tab1',
  tabList,
  tabs = defaultTabs
}: InitializeProps = {}): void => {
  render(
    <Tabs defaultTab={defaultTab} tabList={tabList} tabs={tabs}>
      {defaultChildren}
    </Tabs>
  );
};

const getTabs = (): Array<HTMLElement> => screen.getAllByRole('tab');

describe('Tabs', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('renders all tabs correctly', () => {
    initialize();

    expect(getTabs()).toHaveLength(3);
    expect(getTabs()[0]).toHaveTextContent('Tab 1');
    expect(getTabs()[1]).toHaveTextContent('Tab 2');
    expect(getTabs()[2]).toHaveTextContent('Tab 3');

    // The first tab is the selected one, and the children are rendered
    // below the tab list, in order.
    expect(getTabs()[0]).toHaveAttribute('aria-selected', 'true');
    const contents = ['tab1-content', 'tab2-content', 'tab3-content'].map(
      (testId) => screen.getByTestId(testId)
    );
    contents.forEach((content) => {
      expect(content).toBeVisible();
    });
    expect(
      getTabs()[0].compareDocumentPosition(contents[0]) &
        Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy();
    expect(
      contents[0].compareDocumentPosition(contents[1]) &
        Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy();
    expect(
      contents[1].compareDocumentPosition(contents[2]) &
        Node.DOCUMENT_POSITION_FOLLOWING
    ).toBeTruthy();
  });

  it('displays the default tab as selected', () => {
    initialize();

    expect(getTabs()[0]).toHaveAttribute('aria-selected', 'true');
    expect(getTabs()[1]).toHaveAttribute('aria-selected', 'false');
    expect(getTabs()[2]).toHaveAttribute('aria-selected', 'false');
  });

  it('changes tab when clicked', async () => {
    initialize();

    await userEvent.click(getTabs()[1]);
    expect(getTabs()[1]).toHaveAttribute('aria-selected', 'true');
    expect(getTabs()[0]).toHaveAttribute('aria-selected', 'false');
  });

  it('renders with different default tab', () => {
    initialize({ defaultTab: 'tab2' });

    expect(getTabs()[1]).toHaveAttribute('aria-selected', 'true');
    expect(getTabs()[0]).toHaveAttribute('aria-selected', 'false');
  });

  it('applies correct aria-label to tabs', () => {
    initialize();

    expect(getTabs()[0]).toHaveAttribute('aria-label', 'Tab 1');
    expect(getTabs()[1]).toHaveAttribute('aria-label', 'Tab 2');
    expect(getTabs()[2]).toHaveAttribute('aria-label', 'Tab 3');
  });

  it('renders with JSX element labels', () => {
    const tabsWithJSXLabels = [
      { label: <span>Custom Tab 1</span>, value: 'custom1' },
      { label: <span>Custom Tab 2</span>, value: 'custom2' }
    ] as Array<TabDefinition>;

    initialize({ defaultTab: 'custom1', tabs: tabsWithJSXLabels });

    expect(getTabs()).toHaveLength(2);
    expect(getTabs()[0]).toHaveTextContent('Custom Tab 1');
    expect(getTabs()[1]).toHaveTextContent('Custom Tab 2');
  });

  it('passes through additional tabList props', () => {
    const tabListProps = {
      'data-testid': 'custom-tabs'
    } as TabsProps;

    initialize({ tabList: tabListProps });

    expect(screen.getByTestId('custom-tabs')).toBeInTheDocument();
  });

  it('maintains tab state across multiple clicks', async () => {
    initialize();

    await userEvent.click(getTabs()[2]);
    expect(getTabs()[2]).toHaveAttribute('aria-selected', 'true');

    await userEvent.click(getTabs()[0]);
    expect(getTabs()[0]).toHaveAttribute('aria-selected', 'true');
    expect(getTabs()[2]).toHaveAttribute('aria-selected', 'false');
  });

  it('renders children within TabContext', () => {
    initialize();

    expect(screen.getByTestId('tab1-content')).toBeInTheDocument();
    expect(screen.getByTestId('tab2-content')).toBeInTheDocument();
    expect(screen.getByTestId('tab3-content')).toBeInTheDocument();
  });
});
