import React from "react";
import { storiesOf } from "@storybook/react";
import { DynamicComponentLoader, DynamicComponent } from "../src";

storiesOf("Dynamic Component Loader", module).add(
  "Dynamic Hello World",
  () => (
    <React.Fragment>
      <DynamicComponent componentName={'Example2'} componentUrl={'./example2/index.html'}/>
    </React.Fragment>
  ),
  { notes: "Example of working dynamic component loader" }
).add(
  "Dynamic Hello World From AJAX ",
  () => (
    <React.Fragment>
      <DynamicComponentLoader topologyInfoApiUrl={'http://localhost:3000/components/1'}/>
    </React.Fragment>
  ),
  { notes: "Example of working dynamic component loader" }
);
