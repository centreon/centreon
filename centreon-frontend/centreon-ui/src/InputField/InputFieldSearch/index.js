/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */

import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { withStyles } from '@material-ui/core/styles';
import Paper from '@material-ui/core/Paper';
import InputBase from '@material-ui/core/InputBase';
import IconButton from '@material-ui/core/IconButton';
import CloseIcon from '@material-ui/icons/Close';
import SearchIcon from '@material-ui/icons/Search';

const useStyles = () => ({
  root: {
    padding: '0px 4px',
    display: 'flex',
    alignItems: 'center',
    width: 300,
    borderColor: '#ccc',
    borderRadius: 2,
    borderStyle: 'solid',
    borderWidth: 1,
    color: '#242f3a',
    fontFamily: 'Roboto Regular',
    fontSize: 12,
    boxShadow: 'none',
    position: 'relative',
    backgroundColor: '#f9f9f9',
  },
  paper: {
    padding: 0,
  },
  input: {
    flex: 1,
    fontSize: 13,
  },
  iconButton: {
    padding: 3,
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
  },
});

class InputFieldSearch extends Component {
  state = {
    searchText: '',
  };

  onSearchInputChanged = (event) => {
    const { onChange } = this.props;
    const searchText = event.target.value;

    this.setState(
      {
        searchText,
      },
      () => {
        onChange(searchText);
      },
    );
  };

  render() {
    const { classes, placeholder, onChange, ...rest } = this.props;
    const { searchText } = this.state;
    return (
      <Paper elevation={0} className={classes.paper}>
        <Paper className={classes.root}>
          <IconButton className={classes.iconButton} aria-label="Search">
            <SearchIcon fontSize="small" />
          </IconButton>
          <InputBase
            className={classes.input}
            placeholder={placeholder}
            inputProps={{ 'aria-label': 'Search' }}
            onChange={this.onSearchInputChanged}
            value={searchText}
            {...rest}
          />
          {searchText.length > 0 ? (
            <IconButton
              className={classes.iconButton}
              aria-label="Remove"
              onClick={this.onSearchInputChanged.bind(this, {
                target: { value: '' },
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

InputFieldSearch.defaultProps = {
  placeholder: 'Search',
  style: undefined,
};

InputFieldSearch.propTypes = {
  classes: PropTypes.shape.isRequired,
  placeholder: PropTypes.string,
  onChange: PropTypes.func.isRequired,
  style: PropTypes.shape,
};

export default withStyles(useStyles)(InputFieldSearch);
