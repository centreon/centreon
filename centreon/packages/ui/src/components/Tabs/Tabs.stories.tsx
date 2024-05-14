import { Meta, StoryObj } from '@storybook/react';

import { Typography } from '@mui/material';

import { TabPanel } from './TabPanel';

import { Tabs } from '.';

const meta: Meta<typeof Tabs> = {
  component: Tabs
};

export default meta;
type Story = StoryObj<typeof Tabs>;

const generateTabs = (size: number): Array<{ label: string; value: string }> =>
  Array(size)
    .fill(0)
    .map((_, index) => ({ label: `Tab ${index}`, value: `tab ${index}` }));

export const Default: Story = {
  args: {
    defaultTab: 'tab 0',
    tabs: generateTabs(2)
  },
  render: (args) => (
    <Tabs {...args}>
      {generateTabs(2).map(({ value, label }) => (
        <TabPanel key={value} value={value}>
          <Typography>{label}</Typography>
        </TabPanel>
      ))}
    </Tabs>
  )
};

export const WithTabListProps: Story = {
  args: {
    defaultTab: 'tab 0',
    tabList: {
      textColor: 'inherit',
      variant: 'fullWidth'
    },
    tabs: generateTabs(2)
  },
  render: (args) => (
    <Tabs {...args}>
      {generateTabs(2).map(({ value, label }) => (
        <TabPanel key={value} value={value}>
          <Typography>{label}</Typography>
        </TabPanel>
      ))}
    </Tabs>
  )
};
