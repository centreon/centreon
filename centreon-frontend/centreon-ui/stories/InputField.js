import React from "react";
import { storiesOf } from "@storybook/react";
import classnames from 'classnames';
import styles from '../src/InputField/InputFieldSelect/input-field-select.scss';
import { InputField, InputFieldSelect, InputFieldTextarea, InputFieldMultiSelect, InputFieldSelectCustom } from "../src";

storiesOf("Input Field", module).add(
  
  "Input Field - with title",
  () => 
  <InputField type="text" label="Input field with title" name="test" inputSize="small" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - without title",
  () => 
  <InputField type="text" name="test" inputSize="small" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - without title",
  () => 
  <InputField type="text" name="test" inputSize="small" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field error - without title",
  () => 
  <InputField type="text" name="test" error="The field is mandatory" inputSize="small" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - select",
  () => 
  <InputFieldSelect customClass={classnames(styles["select-option-custom"])} />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - textarea",
  () => 
  <InputFieldTextarea textareaType="small" label="Textarea field label" />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - multiselect custom",
  () => 
  <InputFieldMultiSelect active="active" size="medium"  />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - multiselect custom error",
  () => 
  <InputFieldMultiSelect error="The field is mandatory" size="medium"  />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - select custom",
  () => 
  <InputFieldSelectCustom active="active" size="medium"  />,
  { notes: "A very simple component" }
);

storiesOf("Input Field", module).add(
  
  "Input Field - select custom error",
  () => 
  <InputFieldSelectCustom error="The field is mandatory" size="medium"  />,
  { notes: "A very simple component" }
);