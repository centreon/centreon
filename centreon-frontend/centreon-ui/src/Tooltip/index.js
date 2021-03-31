/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React from 'react';

import { withStyles } from '@material-ui/core/styles';
import IconButton from '@material-ui/core/IconButton';
import Tooltip from '@material-ui/core/Tooltip';

const styles = () => ({
  iconButtonStyle: {
    padding: 5,
  },
  tooltipStyle: {},
});

class TooltipMaterial extends React.Component {
  render() {
    const { label, classes, children, onClick, customStyle } = this.props;
    return (
      <Tooltip className={classes.tooltipStyle} title={label} onClick={onClick}>
        <IconButton
          aria-label={label}
          className={classes.iconButtonStyle}
          style={customStyle}
        >
          {children}
        </IconButton>
      </Tooltip>
    );
  }
}

export default withStyles(styles)(TooltipMaterial);
