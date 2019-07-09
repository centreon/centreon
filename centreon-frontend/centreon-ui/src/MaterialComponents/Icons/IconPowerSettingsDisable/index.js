import React from "react";
import { makeStyles } from "@material-ui/core/styles";
import PowerSettings from "@material-ui/icons/PowerSettingsNew";

const useStyles = makeStyles(theme => ({
  root: {
    display: "flex",
    alignItems: "center",
    textAlign: "left"
  },
  icon: {
    color: "#fff",
    cursor: "pointer",
    backgroundColor: "#707070",
    borderRadius: "50%",
    fontSize: 15,
    padding: 3
  },
  iconNormal: {
    color: "#fff",
    cursor: "pointer",
    backgroundColor: "#707070",
    borderRadius: "50%",
    fontSize: 15,
    padding: 3
  },
  iconWrap: {
    display: "inline-block",
    verticalAlign: "middle",
    height: 23,
    width: 23,
    position: "relative",
    "&::after": {
      content: "''",
      position: "absolute",
      width: 2,
      height: 30,
      background: "#7f7f7f",
      transform: "rotate(140deg)",
      left: 9,
      top: -2,
      zIndex: 1
    },
    "&::before": {
      content: "''",
      position: "absolute",
      width: 4,
      height: 30,
      background: "#fff",
      transform: "rotate(140deg)",
      left: 9,
      top: -3,
      zIndex: 1
    }
  }
}));

function IconPowerSettings({ active, customStyle, ...rest }) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <PowerSettings style={customStyle} className={classes.iconNormal} />
    </span>
  );
}

export default IconPowerSettings;
