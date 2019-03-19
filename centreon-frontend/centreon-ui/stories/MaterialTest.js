import React from "react";
import { storiesOf } from "@storybook/react";
import Button from '@material-ui/core/Button';

storiesOf("Material", module).add(
  "Material - components",
  () => 
  {return (
  <React.Fragment>
    <Button variant="contained" color="primary">
      Hello World
    </Button>
  </React.Fragment>
 )},
  { notes: "A very simple component" }
);