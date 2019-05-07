import React from "react";
import { storiesOf } from "@storybook/react";
import { Navigation } from "../src";

storiesOf("Navigation", module).add(
  "Navigation - items",
  () => (
    <Navigation />
  ),
  { notes: "A very simple component" }
);