import React, { Component } from "react";
import TableCell from "@material-ui/core/TableCell";
import { withStyles } from "@material-ui/core/styles";

const styles = {
  root: {
    padding: "3px 24px 3px 16px",
    fontSize: "13px"
  }
};

export default withStyles(styles)(TableCell);
