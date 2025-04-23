import { ComponentMeta, ComponentStory } from '@storybook/react';

import CopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';

import { ActionVariants } from './models';

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

const actionsWithVariants: Array<{
  label: string;
  onClick: () => void;
  variant?: ActionVariants;
}> = [
  {
    label: 'No variant',
    onClick: (): void => undefined
  },
  {
    label: 'Primary',
    onClick: (): void => undefined,
    variant: 'primary'
  },
  {
    label: 'Secondary',
    onClick: (): void => undefined,
    variant: 'secondary'
  },
  {
    label: 'Success',
    onClick: (): void => undefined,
    variant: 'success'
  },
  {
    label: 'Warning',
    onClick: (): void => undefined,
    variant: 'warning'
  },
  {
    label: 'Error',
    onClick: (): void => undefined,
    variant: 'error'
  },
  {
    label: 'Info',
    onClick: (): void => undefined,
    variant: 'info'
  },
  {
    label: 'Pending',
    onClick: (): void => undefined,
    variant: 'pending'
  }
];

const actionsWithSecondaryLabel = [
  {
    Icon: EditIcon,
    label: 'Edit',
    onClick: (): void => undefined
  },
  {
    Icon: CopyIcon,
    label: 'Duplicate',
    onClick: (): void => undefined,
    secondaryLabel:
      'This is a secondary label that the purpose is to give a small description about an action'
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

export const Variants = Template.bind({});

Variants.args = {
  actions: actionsWithVariants
};

export const SecondaryLabel = Template.bind({});

SecondaryLabel.args = {
  actions: actionsWithSecondaryLabel
};
