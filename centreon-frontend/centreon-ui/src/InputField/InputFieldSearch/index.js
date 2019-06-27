import React, { Component } from "react";
import PropTypes from "prop-types";
import { makeStyles, withStyles } from "@material-ui/core/styles";
import Paper from "@material-ui/core/Paper";
import InputBase from "@material-ui/core/InputBase";
import IconButton from "@material-ui/core/IconButton";
import CloseIcon from "@material-ui/icons/Close";
import SearchIcon from "@material-ui/icons/Search";

const useStyles = theme => ({
  root: {
    padding: "0px 4px",
    display: "flex",
    alignItems: "center",
    width: 300,
    borderColor: "#ccc",
    borderRadius: 2,
    borderStyle: "solid",
    borderWidth: 1,
    color: "#242f3a",
    fontFamily: "Roboto Regular",
    fontSize: 12,
    boxShadow: "none",
    borderRadius: 0,
    position: "relative"
  },
  paper: {
    padding: 0,
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
    position: "absolute",
    height: 2,
    width: "100%",
    backgroundColor: "#009fdf",
    left: "50%",
    right: 0,
    bottom: -1,
    transform: "translateX(-50%)"
  }
});

class InputFieldSearch extends Component {
  state = {
    searchText: ""
  };

  onSearchInputChanged = event => {
    const { onChange } = this.props;
    const searchText = event.target.value;
    this.setState(
      {
        searchText
      },
      () => {
        onChange(searchText);
      }
    );
  };

  render() {
    const { classes, style } = this.props;
    const { searchText } = this.state;
    return (
      <Paper elevation={0} className={classes.paper}>
        <Paper className={classes.root} style={style}>
          <IconButton className={classes.iconButton} aria-label="Search">
            <SearchIcon fontSize="small" />
          </IconButton>
          <InputBase
            className={classes.input}
            placeholder="Search"
            inputProps={{ "aria-label": "Search" }}
            onChange={this.onSearchInputChanged}
            value={searchText}
          />
          {searchText.length > 0 ? (
            <IconButton
              className={classes.iconButton}
              aria-label="Remove"
              onClick={this.onSearchInputChanged.bind(this, {
                target: { value: "" }
              })}
            >
              <CloseIcon fontSize="small" />
            </IconButton>
          ) : null}
          <span className={classes.bottomLine} />
        </Paper>
      </Paper>
    );
  }
}

InputFieldSearch.propTypes = {
  classes: PropTypes.object.isRequired
};

export default withStyles(useStyles)(InputFieldSearch);
