import React from "react";
import {storiesOf} from "@storybook/react";
import {Tab} from "../src";

storiesOf("Tabs", module).add("Tabs - custom", () => <Tab>
  <div label="Configuration">
    Lorem Ipsum dolor sit amet Configuration
  </div>
  <div label="Indicators">
    Lorem Ipsum dolor sit amet Indicators
  </div>
  <div label="Reporting">
    Lorem Ipsum dolor sit amet Reporting
  </div>
  <div label="Escalation">
    Lorem Ipsum dolor sit amet Escalation
  </div>
  <div label="Event Handler">
    Lorem Ipsum dolor sit amet Event Handler
  </div>
</Tab>, {notes: "A very simple component"});
