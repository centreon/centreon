import React from "react";
import { storiesOf } from "@storybook/react";
import { MessageInfo, MessageError } from "../src";

storiesOf("Message", module).add(
  "Message info - red",
  () => (
    <MessageInfo
      messageInfo="red"
      text="Do you want to delete this extension. This, action will remove all associated data."
    />
  ),
  { notes: "A very simple component" }
);

storiesOf("Message", module).add(
  "Message error - red",
  () => (
    <MessageError
      messageError="red"
      text="Generation of configuration has failed, please try again."
    />
  ),
  { notes: "A very simple component" }
);

