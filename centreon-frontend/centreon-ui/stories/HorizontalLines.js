import React from "react";
import { storiesOf } from "@storybook/react";
import { HorizontalLine, HorizontalLineContent } from "../src";

storiesOf("Horizontal Line", module).add(
  "Horizontal Line - regular",
  () => <HorizontalLine />,
  { notes: "A very simple component" }
);

storiesOf("Horizontal Line", module).add(
  "Horizontal Line - content",
  () => <HorizontalLineContent hrTitle="Horizontal line title" />,
  { notes: "A very simple component" }
);
