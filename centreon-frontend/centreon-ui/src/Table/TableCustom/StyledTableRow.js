import React, { Component } from "react";
import TableRow from "@material-ui/core/TableRow";
import PropTypes from "prop-types";
import { withStyles } from "@material-ui/core/styles";

const styles = {
  root: {
    "&:nth-of-type(odd)": {
      backgroundColor: "#f0fbff"
    },
    "&:hover": {
      backgroundColor: "#cae6f1 !important"
    },
    cursor: "pointer"
  }
};

export default withStyles(styles)(TableRow);
