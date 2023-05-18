import { Meta, StoryObj } from "@storybook/react";

import { Settings as SettingsIcon } from "@mui/icons-material";

import { Header } from "./Header";
import { IconButton } from "../Button";

const meta: Meta<typeof Header> = {
  component: Header,
};

export default meta;
type Story = StoryObj<typeof Header>;

export const Default: Story = {
  args: {
    title: "Header",
  },
};

export const WithNav: Story = {
  args: {
    nav: (
      <>
        <IconButton icon={<SettingsIcon />} size="small" variant="ghost" />
      </>
    ),
    title: "Header with nav",
  },
};
