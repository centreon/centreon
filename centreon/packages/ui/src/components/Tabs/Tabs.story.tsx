import { Typography } from '@mui/material';

import { Tabs } from '.';
import { TabPanel } from './TabPanel';

interface Props {
  fullWidth?: boolean;
}

export const Default = ({ fullWidth = false }: Props): JSX.Element => (
  <Tabs
    defaultTab="tab 0"
    tabList={fullWidth ? { variant: 'fullWidth' } : undefined}
    tabs={[
      { label: 'Tab 0', value: 'tab 0' },
      { label: 'Tab 1', value: 'tab 1' }
    ]}
  >
    <TabPanel value="tab 0">
      <Typography>Tab 0</Typography>
    </TabPanel>
    <TabPanel value="tab 1">
      <Typography>Tab 1</Typography>
    </TabPanel>
  </Tabs>
);
