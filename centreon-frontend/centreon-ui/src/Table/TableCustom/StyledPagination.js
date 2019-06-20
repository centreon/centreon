import React, { Component } from "react";
import TablePagination from "@material-ui/core/TablePagination";
import { withStyles } from "@material-ui/core/styles";

const styles = {
    toolbar: {
		height: '32px',
		minHeight: 'auto'
	  }
};

export default withStyles(styles)(TablePagination);
