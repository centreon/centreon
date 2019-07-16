/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import { withStyles } from '@material-ui/core/styles';
import Switch from '@material-ui/core/Switch';

const styles = () => ({
  switchBase: {
    color: '#c7c8c9',
    '&$checked': {
      color: '#0072CE',
      '&:hover': {
        backgroundColor: 'rgba(0, 114, 206, 0.08)',
      },
    },
    '&$checked + $track': {
      backgroundColor: '#0072CE',
      opacity: '.4',
    },
  },
  checked: {},
  track: {},
});

// eslint-disable-next-line no-unused-vars
class CustomSwitch extends Component {
  render() {
    const { classes, ...rest } = this.props;
    return (
      <Switch color="primary" className={classes.switchBase} {...rest}/>
    );
  }
}

export default withStyles(styles)(Switch);
