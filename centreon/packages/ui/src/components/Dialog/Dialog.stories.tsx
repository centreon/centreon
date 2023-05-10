import { Meta, StoryObj } from '@storybook/react';
import { Dialog } from './Dialog';


const meta: Meta<typeof Dialog> = {
  component: Dialog
};

export default meta;
type Story = StoryObj<typeof Dialog>

export const Default: Story = {
  args: {
    children: 'Content area',
    open: true
  }
};