import { TabPanel as MuiTabPanel } from '@mui/lab';

import { useTabsStyles } from './Tab.styles';

type Props = {
  children: JSX.Element;
  value: string;
};

export const TabPanel = ({ children, value }: Props): JSX.Element => {
  const { classes } = useTabsStyles();

  return (
    <MuiTabPanel
      className={classes.tabPanel}
      data-tabPanel={value}
      value={value}
    >
      {children}
    </MuiTabPanel>
  );
};
