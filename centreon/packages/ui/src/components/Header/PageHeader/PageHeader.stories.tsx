import { Meta, StoryObj } from '@storybook/react';

import {
  Settings as SettingsIcon,
  StarOutline as StarOutlineIcon
} from '@mui/icons-material';

import { Button, IconButton } from '../../Button';
import { Menu } from '../../Menu';

import { PageHeader } from './index';

type PageHeaderComponent = typeof PageHeader & typeof PageHeader.Title;

const meta: Meta<typeof PageHeader> = {
  component: PageHeader
};

export default meta;
type Story = StoryObj<PageHeaderComponent>;

export const Default: Story = {
  args: {
    title: 'Page header'
  },
  render: (args) => (
    <PageHeader>
      <PageHeader.Main>
        <PageHeader.Title {...args} />
      </PageHeader.Main>
      <PageHeader.Actions>
        <Button
          icon={<SettingsIcon />}
          iconVariant="start"
          size="small"
          variant="ghost"
        >
          Manage
        </Button>
      </PageHeader.Actions>
    </PageHeader>
  )
};

export const WithDescription: Story = {
  args: {
    description:
      'Eu aliquip quis in minim laboris occaecat quis nostrud occaecat. Cupidatat consectetur non Lorem eiusmod ut cupidatat dolor enim cillum ex enim irure velit. ',
    title: 'Nulla ex dolore tempor magna ex'
  },
  render: Default.render
};

export const WithMenu: Story = {
  args: {
    ...WithDescription.args,
    actions: (
      <IconButton icon={<StarOutlineIcon />} size="small" variant="ghost" />
    )
  },
  render: (args) => (
    <PageHeader>
      <PageHeader.Main>
        <PageHeader.Menu>
          <Menu>
            <Menu.Button />
            <Menu.Items>
              <Menu.Item>Menu Item</Menu.Item>
              <Menu.Item>Menu Item</Menu.Item>
              <Menu.Item>Menu Item</Menu.Item>
            </Menu.Items>
          </Menu>
        </PageHeader.Menu>
        <PageHeader.Title {...args} />
      </PageHeader.Main>
      <PageHeader.Actions>
        <Button
          icon={<SettingsIcon />}
          iconVariant="start"
          size="small"
          variant="ghost"
        >
          Manage
        </Button>
      </PageHeader.Actions>
    </PageHeader>
  )
};
