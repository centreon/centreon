import { Meta, StoryObj } from '@storybook/react';

import { Add as AddIcon } from '@mui/icons-material';

import { Button } from '../Button';

import { Menu } from '.';

const meta: Meta<typeof Menu> = {
  component: Menu
};

export default meta;
type Story = StoryObj<typeof Menu>;

export const Default: Story = {
  args: {
    children: (
      <Menu.Items>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item isActive isDisabled>
          Menu Item
        </Menu.Item>
        <Menu.Item isDisabled>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Divider />
        <Menu.Item>Add item...</Menu.Item>
      </Menu.Items>
    ),
    isOpen: true
  }
};

export const WithMenuButton: Story = {
  args: {
    children: (
      <>
        <Menu.Button />
        {Default.args?.children}
      </>
    )
  }
};

export const WithCustomActionButton: Story = {
  args: {
    children: (
      <Menu.Items>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Item isActive isDisabled>
          Menu Item
        </Menu.Item>
        <Menu.Item isDisabled>Menu Item</Menu.Item>
        <Menu.Item>Menu Item</Menu.Item>
        <Menu.Divider />
        <Menu.Item>
          <Button icon={<AddIcon />} iconVariant="start" variant="ghost">
            Add item
          </Button>
        </Menu.Item>
      </Menu.Items>
    ),
    isOpen: true
  }
};
