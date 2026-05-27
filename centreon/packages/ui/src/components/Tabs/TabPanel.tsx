import { TabPanel as MuiTabPanel } from '@mui/lab';

type Props = {
  children: JSX.Element;
  value: string;
};

export const TabPanel = ({ children, value }: Props): JSX.Element => (
  <MuiTabPanel className="p-0 pt-2" data-tabPanel={value} value={value}>
    {children}
  </MuiTabPanel>
);
