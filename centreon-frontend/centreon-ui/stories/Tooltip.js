import React from "react";
import { storiesOf } from "@storybook/react";
import { InfoTooltip } from "../src";

storiesOf("Tooltip", module).add("Tooltip ", () => <InfoTooltip />, {
  notes: "A very simple component"
});