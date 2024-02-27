import { Meta, StoryObj } from '@storybook/react';

import GridItem from './GridItem';

const meta: Meta<typeof GridItem> = {
  component: GridItem
};

export default meta;
type Story = StoryObj<typeof GridItem>;

export const Default: Story = {
  args: {
    description: 'DataTable item description',
    title: 'DataTable item'
  }
};

export const WithActions: Story = {
  args: {
    ...Default.args,
    hasActions: true,
    hasCardAction: true
  }
};
