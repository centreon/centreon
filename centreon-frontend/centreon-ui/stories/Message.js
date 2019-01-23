import React from "react";
import { storiesOf } from "@storybook/react";
import { MessageInfo } from "../src";

storiesOf("Message", module).add(
  "Message info - red",
  () => <MessageInfo messageInfo="red" text="Do you want to delete this extension. This, action will remove all associated data." />,
  { notes: "A very simple component" }
);
