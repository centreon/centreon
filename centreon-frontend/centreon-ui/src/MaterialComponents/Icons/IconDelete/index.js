import React from "react";
import { makeStyles } from "@material-ui/core/styles";
import Delete from "@material-ui/icons/Delete";

const useStyles = makeStyles(() => ({
  root: {
    display: "flex",
    justifyContent: "center",
    alignItems: "center"
  },
  icon: {
    color: "#707070",
    cursor: "pointer"
  },
  iconWrap: {
    display: "inline-block",
    verticalAlign: "middle",
    height: 24
  }
}));

function IconDelete({ customStyle, ...rest }) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <Delete style={customStyle} className={classes.icon} />
    </span>
  );
}

export default IconDelete;
