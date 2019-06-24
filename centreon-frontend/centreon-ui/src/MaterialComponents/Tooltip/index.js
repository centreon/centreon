import React from "react";
import { withStyles } from "@material-ui/core/styles";
import IconButton from "@material-ui/core/IconButton";
import Tooltip from "@material-ui/core/Tooltip";

const styles = () => ({
  tooltipStyle: {},
  iconButtonStyle: {
    padding: 5
  }
});

class TooltipMaterial extends React.Component {
  render() {
    const { label, classes, children } = this.props;
    return (
      <Tooltip title={label} className={classes.tooltipStyle}>
        <IconButton aria-label={label} className={classes.iconButtonStyle}>
          {children}
        </IconButton>
      </Tooltip>
    );
  }
}

export default withStyles(styles)(TooltipMaterial);
