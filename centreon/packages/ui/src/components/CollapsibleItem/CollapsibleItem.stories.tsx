import { Meta, StoryObj } from '@storybook/react';

import { Checkbox, Typography } from '@mui/material';

import { CollapsibleItem } from './CollapsibleItem';

const meta: Meta<typeof CollapsibleItem> = {
  component: CollapsibleItem
};

export default meta;
type Story = StoryObj<typeof CollapsibleItem>;

export const Default: Story = {
  args: {
    children: 'Label',
    title: 'Title'
  }
};

export const ExpandedByDefault: Story = {
  args: {
    children: 'Label',
    defaultExpanded: true,
    title: 'Title'
  }
};

export const customizedTitle: Story = {
  args: {
    children: 'Label',
    defaultExpanded: false,
    title: <Typography>Title</Typography>
  }
};

export const customizedTitleAndCompact: Story = {
  args: {
    children: 'Label',
    compact: true,
    defaultExpanded: false,
    title: (
      <div
        style={{ alignItems: 'center', display: 'flex', flexDirection: 'row' }}
      >
        <Checkbox size="small" />
        <Typography>Title compact</Typography>
      </div>
    )
  }
};
