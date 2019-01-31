import React from "react";
import { storiesOf } from "@storybook/react";
import { Checkbox } from "../src";

storiesOf("Checkbox Button", module).add(
  
  "Checkbox Button - with title",
  () => 
  <Checkbox label="test" name="test" />,
  { notes: "A very simple component" }
);

storiesOf("Checkbox Button", module).add(
  
  "Checkbox Button Checked - with title",
  () => 
  <Checkbox label="test" checked={true} name="test" id='test' />,
  { notes: "A very simple component" }
);

storiesOf("Checkbox Button", module).add(
  
  "Checkbox Button - without title",
  () => 
  <Checkbox name="test" />,
  { notes: "A very simple component" }
);

storiesOf("Checkbox Button", module).add(
  
  "Checkbox Button Checked - without title",
  () => 
  <Checkbox checked={true} name="test" />,
  { notes: "A very simple component" }
);