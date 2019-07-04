import React, { Component } from 'react';
import Checkbox from '@material-ui/core/Checkbox';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  root: {
    '&$checked': {
      color: '#232f39',
    },
  },
  checked: {},
};

export default withStyles(styles)(Checkbox);
