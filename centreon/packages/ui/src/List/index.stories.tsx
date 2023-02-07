import { ComponentMeta, ComponentStory } from '@storybook/react';

import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import CopyIcon from '@mui/icons-material/ContentCopy';

import List from '.';

const actions = [
  {
    Icon: CopyIcon,
    label: 'Duplicate',
    onClick: (): void => undefined
  },
  {
    Icon: EditIcon,
    label: 'Edit',
    onClick: (): void => undefined
  },
  {
    Icon: DeleteIcon,
    label: 'Delete',
    onClick: (): void => undefined
  }
];

export default {
  argTypes: {},
  component: List,
  title: 'List'
} as ComponentMeta<typeof List>;

const Template: ComponentStory<typeof List> = (args) => <List {...args} />;

export const Playground = Template.bind({});

Playground.args = {
  actions
};
