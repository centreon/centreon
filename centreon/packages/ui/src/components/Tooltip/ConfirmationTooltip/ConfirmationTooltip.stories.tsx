import { Meta, StoryObj } from '@storybook/react';

import { Delete as DeleteIcon, Save as SaveIcon } from '@mui/icons-material';

import { IconButton } from '../..';

import { ConfirmationTooltip } from './ConfirmationTooltip';

const meta: Meta<typeof ConfirmationTooltip> = {
  component: ConfirmationTooltip
};

export default meta;
type Story = StoryObj<typeof ConfirmationTooltip>;

export const Default: Story = {
  args: {
    children: ({ toggleTooltip }) => (
      <IconButton icon={<SaveIcon />} onClick={toggleTooltip} />
    ),
    labels: {
      cancel: 'Cancel',
      confirm: {
        label: 'Save'
      }
    },
    onConfirm: () => undefined
  }
};

export const WithConfirmVariant: Story = {
  args: {
    children: ({ toggleTooltip }) => (
      <IconButton icon={<DeleteIcon color="error" />} onClick={toggleTooltip} />
    ),
    confirmVariant: 'error',
    labels: {
      cancel: 'Cancel',
      confirm: {
        label: 'Delete'
      }
    },
    onConfirm: () => undefined
  }
};

export const WithSecondaryLabel: Story = {
  args: {
    children: ({ toggleTooltip }) => (
      <IconButton icon={<DeleteIcon color="error" />} onClick={toggleTooltip} />
    ),
    confirmVariant: 'error',
    labels: {
      cancel: 'Cancel',
      confirm: {
        label: 'Delete',
        secondaryLabel: 'This action will delete the current item'
      }
    },
    onConfirm: () => undefined
  }
};
