import { ComponentMeta, ComponentStory } from '@storybook/react';

import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import CopyIcon from '@mui/icons-material/ContentCopy';

import ActionsList from '.';

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
  component: ActionsList,
  title: 'ActionsList'
} as ComponentMeta<typeof ActionsList>;

const Template: ComponentStory<typeof ActionsList> = (args) => (
  <ActionsList {...args} />
);

export const Playground = Template.bind({});

Playground.args = {
  actions
};
