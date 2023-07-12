import { Meta, StoryObj } from '@storybook/react';

import { ListItem } from '.';

const meta: Meta<typeof ListItem> = {
  component: ListItem
};

export default meta;
type Story = StoryObj<typeof ListItem>;

export const Default: Story = {
  args: {
    children: 'Default'
  }
};

export const WithTextAndAvatar: Story = {
  args: {
    children: (
      <>
        <ListItem.Avatar>AV</ListItem.Avatar>
        <ListItem.Text
          primaryText="Primary text"
          secondaryText="Secondary text"
        />
      </>
    )
  }
};

export const AsLoadingState: Story = {
  args: {
    children: (
      <>
        <ListItem.Avatar.Skeleton />
        <ListItem.Text.Skeleton secondaryText />
      </>
    )
  }
};
