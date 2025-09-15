import { JSX, useCallback, useState } from 'react';

import { TabContext } from '@mui/lab';
import { Tabs as MuiTabs, Tab, TabsProps } from '@mui/material';

import { useStyles } from './ConnectionInitiated.styles';

type Props = {
  children: Array<JSX.Element>;
  defaultTab: string;
  tabList?: TabsProps;
  tabs: Array<{
    label: string | JSX.Element;
    value: string;
  }>;
};

export const Tabs = ({
  children,
  defaultTab,
  tabs,
  tabList
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const [selectedTab, setSelectedTab] = useState(defaultTab);

  const changeTab = useCallback((_, newValue: string): void => {
    setSelectedTab(newValue);
  }, []);

  return (
    <TabContext value={selectedTab}>
      <MuiTabs
        classes={{
          indicator: classes.indicator,
          root: classes.tabs
        }}
        onChange={changeTab}
        value={selectedTab}
        variant="fullWidth"
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
      </MuiTabs>
      {children}
    </TabContext>
  );
};
