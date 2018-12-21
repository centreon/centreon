import React from "react";
import { storiesOf } from "@storybook/react";
import { Subtitle } from "../src";

storiesOf("Subtitle", module).add(
  "Subtitle - custom",
  () => <Subtitle label="Test" />,
  { notes: "A very simple component" }
);
