import React from "react";
import { storiesOf } from "@storybook/react";
import { DynamicComponentLoader } from "../src";

storiesOf("Dynamic Component Loader", module).add(
  "Dynamic Hello World",
  () => (
    <React.Fragment>
      <DynamicComponentLoader topologyApiUrl={'http://localhost:3000/components/1'}/>
    </React.Fragment>
  ),
  { notes: "Example of working dynamic component loader" }
);
