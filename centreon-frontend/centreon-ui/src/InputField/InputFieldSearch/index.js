import React from "react";
import { makeStyles } from "@material-ui/core/styles";
import Paper from "@material-ui/core/Paper";
import InputBase from "@material-ui/core/InputBase";
import IconButton from "@material-ui/core/IconButton";
import CloseIcon from "@material-ui/icons/Close";
import SearchIcon from "@material-ui/icons/Search";
import DirectionsIcon from "@material-ui/icons/Directions";

const useStyles = makeStyles(theme => ({
  root: {
    padding: "0px 4px",
    display: "flex",
    alignItems: "center",
    width: 300,
    borderColor: '#ccc',
    borderRadius: 2,
    borderStyle: 'solid',
    borderWidth: 1,
    color: '#242f3a',
    fontFamily: "Roboto Regular",
    fontSize: 12,
    boxShadow: 'none',
    borderRadius: 0,
    position: 'relative',
  },
  paper: {
    padding: theme.spacing(1, 2),
  },
  input: {
    marginLeft: 8,
    flex: 1
  },
  iconButton: {
    padding: 3
  },
  bottomLine: {
    content: '""',
    position: 'absolute',
    height: 2,
    width: '100%',
    backgroundColor: '#009fdf',
    left: '50%',
    right: 0,
    bottom: -1,
    transform: 'translateX(-50%)',
  }
}));

export default function InputFieldSearch() {
  const classes = useStyles();

  return (
    <Paper elevation={0} className={classes.paper}>
      <Paper className={classes.root}>
        <IconButton className={classes.iconButton} aria-label="Search">
          <SearchIcon fontSize='small' />
        </IconButton>
        <InputBase
          className={classes.input}
          placeholder="Search"
          inputProps={{ "aria-label": "Search" }}
        />
        <IconButton className={classes.iconButton} aria-label="Menu">
          <CloseIcon fontSize='small' />
        </IconButton>
        <span className={classes.bottomLine}></span>
      </Paper>
    </Paper>
  );
}
