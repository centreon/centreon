import React from "react";
import {storiesOf} from "@storybook/react";
import {Table, TableDynamic, Title, Button} from "../src";

storiesOf("Table", module).add("Table - custom", () => <Table/>, {notes: "A very simple component"});
storiesOf("Table", module).add("Table Dynamic - custom", () => <React.Fragment>
  <Title titleColor="host" label="Resource discovry wizard" />
  <TableDynamic/>
  <div className="text-right">
    <Button
      label="SAVE"
      buttonType="validate"
      color="blue normal mr-2"
    />
    <Button
      label="SAVE & MONITOR"
      buttonType="validate"
      color="blue normal"
    />
  </div>
</React.Fragment>, {notes: "A very simple component"});