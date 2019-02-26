import React from "react";
import {storiesOf} from "@storybook/react";
import {Table} from "../src";

storiesOf("Table", module).add("Table - custom", () => <Table/>, {notes: "A very simple component"});
