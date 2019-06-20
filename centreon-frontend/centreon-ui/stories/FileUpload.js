import React from "react";
import { storiesOf } from "@storybook/react";
import { FileUpload } from "../src";

storiesOf("File Upload", module).add("File Upload", () => <FileUpload />, {
  notes: "A very simple component"
});
