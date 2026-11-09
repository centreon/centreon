import { TabContext } from '@mui/lab';
import { Tabs as MuiTabs, Tab, type TabsProps } from '@mui/material';

import { useCallback, useState } from 'react';

import { useTabsStyles } from './Tab.styles';

import '../../ThemeProvider/tailwindcss.css';

export interface TabI {
  label: string;
  value: string;
}

type Props = {
  children?: Array<JSX.Element>;
  defaultTab: string;
  selectedTab?: string;
  tabList?: TabsProps;
  variant?: 'standard' | 'scrollable' | 'fullWidth';
  scrollButtons?: boolean | 'auto';
  tabs: Array<TabI>;
  onChange?: (newValue: string) => void;
};

export const Tabs = ({
  children,
  defaultTab,
  selectedTab: controlledTab,
  tabs,
  tabList,
  variant,
  scrollButtons = 'auto',
  onChange
}: Props): JSX.Element => {
  const { classes } = useTabsStyles();

  const [internalTab, setSelectedTab] = useState(defaultTab);

  // The parent drives the tab when it passes one (e.g. a form following its
  // scroll); otherwise the tab keeps its own state.
  const selectedTab = controlledTab ?? internalTab;

  const changeTab = useCallback(
    (_: React.SyntheticEvent, newValue: string): void => {
      if (onChange) onChange(newValue);

      setSelectedTab(newValue);
    },
    [onChange]
  );

  const selectedTabStyle = ' font-bold text-primary-main';
  const defaultTabStyle = ' font-normal';

  return (
    <TabContext value={selectedTab}>
      <MuiTabs
        classes={{
          indicator: classes.indicator,
          root: classes.tabs
        }}
        onChange={changeTab}
        scrollButtons={scrollButtons}
        value={selectedTab}
        variant={variant}
        {...tabList}
      >
        {tabs.map(({ value, label }) => (
          <Tab
            aria-label={label}
            className={`${classes.tab} ${selectedTab === value ? selectedTabStyle : defaultTabStyle}`}
            data-testid={`tab-${value}`}
            key={value}
            label={label}
            value={value}
          />
        ))}
      </MuiTabs>
      {children}
    </TabContext>
  );
};
