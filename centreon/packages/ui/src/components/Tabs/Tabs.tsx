import { useCallback, useState } from 'react';

import { TabContext, TabList, TabListProps } from '@mui/lab';
import { Tab } from '@mui/material';

import { useTabsStyles } from './Tab.styles';

type Props = {
  children: Array<JSX.Element>;
  defaultTab: string;
  tabList?: TabListProps;
  tabs: Array<{
    label: string;
    value: string;
  }>;
};

export const Tabs = ({
  children,
  defaultTab,
  tabs,
  tabList
}: Props): JSX.Element => {
  const { classes } = useTabsStyles();

  const [selectedTab, setSelectedTab] = useState(defaultTab);

  const changeTab = useCallback((_, newValue: string): void => {
    setSelectedTab(newValue);
  }, []);

  return (
    <TabContext value={selectedTab}>
      <TabList
        classes={{
          indicator: classes.indicator,
          root: classes.tabs
        }}
        onChange={changeTab}
        {...tabList}
      >
        {tabs.map(({ value, label }) => (
          <Tab
            aria-label={label}
            className={classes.tab}
            key={value}
            label={label}
            value={value}
          />
        ))}
      </TabList>
      {children}
    </TabContext>
  );
};
