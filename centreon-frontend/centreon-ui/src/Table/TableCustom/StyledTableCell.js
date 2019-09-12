/* eslint-disable no-unused-vars */

import React, { Component } from 'react';
import TableCell from '@material-ui/core/TableCell';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  head: {
    backgroundColor: '#009fdf',
    color: '#fff',
    height: '24px',
    padding: '6px 24px 6px 16px',
    zIndex: 2,
    top: 0,
    '&:hover': {
      color: '#fff',
    },
  },
  body: {
    fontSize: 12,
    textAlign: 'left',
  },
};

export default withStyles(styles)(TableCell);
