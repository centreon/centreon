import React, { Component } from "react";
import { withStyles } from "@material-ui/core/styles";
import Switch from "@material-ui/core/Switch";

const styles = () => ({
  switchBase: {
    color: "#c7c8c9",
    "&$checked": {
      color: "#0072CE",
      "&:hover": {
        backgroundColor: "rgba(0, 114, 206, 0.08)"
      }
    },
    "&$checked + $track": {
      backgroundColor: "#0072CE",
      opacity: ".4"
    }
  },
  checked: {},
  track: {}
});

class CustomSwitch extends Component {
  render() {
    const { classes } = this.props;
    return (
      <Switch value="checkedB" color="primary" className={classes.switchBase} />
    );
  }
}

export default withStyles(styles)(Switch);
