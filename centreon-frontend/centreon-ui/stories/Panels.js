import React from "react";
import { storiesOf } from "@storybook/react";
import {Panels} from "../src";

storiesOf("Panels", module).add(
  "Panels",
  () => (
    <Panels panelTtype="small" />
  ),
  { notes: "A very simple component" }
);
