import React, { Component } from 'react';
import TableSortLabel from '@material-ui/core/TableSortLabel';
import PropTypes from 'prop-types';
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
