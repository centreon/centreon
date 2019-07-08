/* eslint-disable no-unused-vars */

import React, { Component } from 'react';
import TableCell from '@material-ui/core/TableCell';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  root: {
    maxWidth: 90,
    whiteSpace: 'nowrap',
    textOverflow: 'ellipsis',
    overflow: 'hidden',
    fontSize: 13,
    padding: '3px 24px 3px 16px',
  },
};

export default withStyles(styles)(TableCell);
