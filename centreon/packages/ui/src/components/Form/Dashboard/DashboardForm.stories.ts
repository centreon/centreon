import { Meta, StoryObj } from "@storybook/react";

import { DashboardForm } from "./DashboardForm";

const meta: Meta<typeof DashboardForm> = {
  component: DashboardForm,
};

export default meta;
type Story = StoryObj<typeof DashboardForm>;

export const Default: Story = {
  args: {
    labels: {
      actions: {
        cancel: "Cancel",
        submit: {
          create: "Create",
          update: "Update",
        },
      },
      entity: {
        description: "Description",
        name: "Name",
      },
      title: {
        create: "Create dashboard",
        update: "Update dashboard",
      },
    },
  },
};

export const AsUpdateVariant: Story = {
  args: {
    ...Default.args,
    resource: {
      description: "Description 1",
      name: "Dashboard 1",
    },
    variant: "update",
  },
};
