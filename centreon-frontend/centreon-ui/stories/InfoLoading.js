import React from "react";
import { storiesOf } from "@storybook/react";
import { InfoLoading } from "../src";

storiesOf("Info", module).add(
  "Info - loading",
  () => <InfoLoading
    label="Loading job may take some while"
    infoType="bordered"
    color="orange"
    iconActionType="clock"
    iconColor="orange"
  />,
  { notes: "A very simple component" }
);