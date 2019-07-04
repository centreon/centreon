import React from "react";
import { makeStyles } from "@material-ui/core/styles";
import Paper from "@material-ui/core/Paper";
import Divider from "@material-ui/core/Divider";

const useStyles = makeStyles(theme => ({
  root: {
    width: "100%",
    maxWidth: "360px",
    backgroundColor: theme.palette.background.paper
  },
  paper: {
    padding: theme.spacing(1, 2)
  }
}));

export default function ListDividers() {
  const classes = useStyles();

  return (
    <Paper elevation={0} className={classes.paper}>
      <Divider />
    </Paper>
  );
}
