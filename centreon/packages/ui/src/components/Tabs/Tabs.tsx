import { useCallback, useState } from 'react';

import { TabContext } from '@mui/lab';
import { Tabs as MuiTabs, Tab, TabsProps } from '@mui/material';

import { useTabsStyles } from './Tab.styles';

import '../../ThemeProvider/tailwindcss.css';

export interface TabI {
  label: string;
  value: string;
}

type Props = {
  children?: Array<JSX.Element>;
  defaultTab: string;
  tabList?: TabsProps;
  variant?: 'standard' | 'scrollable' | 'fullWidth';
  tabs: TabI[];
  onChange?: (newValue: string) => void;
};

export const Tabs = ({
  children,
  defaultTab,
  tabs,
  tabList,
  variant,
  onChange
}: Props): JSX.Element => {
  const { classes } = useTabsStyles();

  const [selectedTab, setSelectedTab] = useState(defaultTab);

  const changeTab = useCallback(
    (_, newValue: string): void => {
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
        variant={variant}
        classes={{
          indicator: classes.indicator,
          root: classes.tabs
        }}
        onChange={changeTab}
        value={selectedTab}
        {...tabList}
      >
        {tabs.map(({ value, label }) => (
          <Tab
            aria-label={label}
            className={`${classes.tab} ${selectedTab === value ? selectedTabStyle : defaultTabStyle}`}
            key={value}
            label={label}
            value={value}
            data-testid={`tab-${value}`}
          />
        ))}
      </MuiTabs>
      {children}
    </TabContext>
  );
};
