import { Meta, StoryObj } from '@storybook/react';

import { Default as HeaderStory } from './Header/DialogHeader.stories';

import { Dialog } from '.';

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

export const WithHeader: Story = {
  args: {
    ...Default.args
  },
  render: (args) => (
    <Dialog {...args}>
      <Dialog.Header {...HeaderStory.args} />
      {args.children}
    </Dialog>
  )
};
