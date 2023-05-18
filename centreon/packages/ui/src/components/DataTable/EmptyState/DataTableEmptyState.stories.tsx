import { Meta, StoryObj } from "@storybook/react";

import { DataTableEmptyState } from "./DataTableEmptyState";

const meta: Meta<typeof DataTableEmptyState> = {
  component: DataTableEmptyState,
};

export default meta;
type Story = StoryObj<typeof DataTableEmptyState>;

export const Default: Story = {
  args: {
    labels: {
      actions: {
        create: "Create item",
      },
      title: "No items found",
    },
  },
};
