import { Meta, StoryObj } from '@storybook/react';

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
