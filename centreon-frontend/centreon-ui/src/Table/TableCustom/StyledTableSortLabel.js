/* eslint-disable no-unused-vars */

import React, { Component } from 'react';
import TableSortLabel from '@material-ui/core/TableSortLabel';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  root: {
    color: '#fff !important',
  },
  icon: {
    color: '#fff !important',
  },
  active: {
    color: '#fff !important',
  },
};

export default withStyles(styles)(TableSortLabel);
