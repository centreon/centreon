import { TabPanel as MuiTabPanel } from '@mui/lab';

type Props = {
  children: JSX.Element;
  value: string;
};

export const TabPanel = ({ children, value }: Props): JSX.Element => (
  <MuiTabPanel
    data-tabPanel={value}
    sx={(theme) => ({ padding: theme.spacing(1, 0, 0) })}
    value={value}
  >
    {children}
  </MuiTabPanel>
);
