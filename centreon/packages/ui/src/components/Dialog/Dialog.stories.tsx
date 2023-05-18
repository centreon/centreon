import { Meta, StoryObj } from "@storybook/react";

import { Dialog } from ".";
import { Default as HeaderStory } from "./Header/DialogHeader.stories";

const meta: Meta<typeof Dialog> = {
  component: Dialog,
};

export default meta;
type Story = StoryObj<typeof Dialog>;

export const Default: Story = {
  args: {
    children: "Content area",
    open: true,
  },
};

export const WithHeader: Story = {
  render: (args) => (
    <Dialog {...args}>
      <Dialog.Header {...HeaderStory.args} />
      {args.children}
    </Dialog>
  ),
  args: {
    ...Default.args,
  },
};
