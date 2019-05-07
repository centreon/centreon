import React from "react";
import { storiesOf } from "@storybook/react";
import { Sidebar, Navigation, Logo, LogoMini, SidebarToggle } from "../src";

storiesOf("Sidebar", module).add(
  "Sidebar",
  () => (
    <Sidebar />
  ),
  { notes: "A very simple component" }
);