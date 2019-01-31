import React from "react";
import { storiesOf } from "@storybook/react";
import { InputField } from "../src";

storiesOf("Input Field", module).add(
  
  "Input Field - with title",
  () => 
  <InputField type="text" label="Input field with title" name="test" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - without title",
  () => 
  <InputField type="text" name="test" />,
  { notes: "A very simple component" }
);