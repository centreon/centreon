import { Meta, StoryObj } from '@storybook/react';

import { Dialog } from './Dialog';
import { DialogTitle } from './DialogTitle';

const meta: Meta<typeof Dialog> = {
  component: Dialog
};

export default meta;
type Story = StoryObj<typeof Dialog>;

export const Default: Story = {
  args: {
    children: 'Content area',
    open: true
  }
};

export const WithTitle: Story = {
  args: {
    children: (
      <>
        <DialogTitle>Title</DialogTitle>
        Content area
      </>
    ),
    open: true
  }
};
