import { Meta, StoryObj } from "@storybook/react";

import { Add as AddIcon } from "@mui/icons-material";

import { Button } from "./Button";

const meta: Meta<typeof Button> = {
  component: Button,
};

export default meta;
type Story = StoryObj<typeof Button>;

export const Default: Story = {
  args: {
    children: "Label",
    "aria-label": "button",
  },
};

export const WithIcon: Story = {
  args: {
    ...Default.args,
    icon: <AddIcon />,
    iconVariant: "start", // TODO Icon component
  },
};
