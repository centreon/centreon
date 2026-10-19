import { Tabs } from './Tabs';

interface Props {
  defaultTab?: string;
  tabListTestId?: string;
}

const tabs = [
  { ariaLabel: 'Tab 1', label: 'Tab 1', value: 'tab1' },
  { ariaLabel: 'Tab 2', label: 'Tab 2', value: 'tab2' },
  { ariaLabel: 'Tab 3', label: 'Tab 3', value: 'tab3' }
];

const contents = tabs.map(({ label, value }) => (
  <div data-testid={`${value}-content`} key={value}>
    {label} Content
  </div>
));

export const Default = ({
  defaultTab = 'tab1',
  tabListTestId
}: Props): JSX.Element => (
  <Tabs
    defaultTab={defaultTab}
    // data-testid is forwarded to the tab list, outside the MUI props type.
    tabList={
      tabListTestId
        ? ({ 'data-testid': tabListTestId } as Record<string, string>)
        : undefined
    }
    tabs={tabs}
  >
    {contents}
  </Tabs>
);

export const WithElementLabels = (): JSX.Element => (
  <Tabs
    defaultTab="custom1"
    tabs={[
      {
        ariaLabel: 'Custom Tab 1',
        label: <span>Custom Tab 1</span>,
        value: 'custom1'
      },
      {
        ariaLabel: 'Custom Tab 2',
        label: <span>Custom Tab 2</span>,
        value: 'custom2'
      }
    ]}
  >
    {[<div key="custom1" />, <div key="custom2" />]}
  </Tabs>
);
