import React from 'react';

import { createStyles, makeStyles } from '@material-ui/core/styles';
import Paper from '@material-ui/core/Paper';
import InputBase from '@material-ui/core/InputBase';
import IconButton from '@material-ui/core/IconButton';
import SearchIcon from '@material-ui/icons/Search';
import PropTypes from 'prop-types';

const useStyles = makeStyles(
  createStyles({
    root: {
      display: 'flex',
      alignItems: 'center',
    },
    input: {
      marginLeft: 8,
      flex: 1,
    },
    iconButton: {
      padding: 4,
    },
  }),
);

const InputSearch = ({ placeholder, onSearch }) => {
  const classes = useStyles();

  const onChange = (event) => {
    onSearch(event.target.value);
  };

  return (
    <Paper className={classes.root}>
      <IconButton className={classes.iconButton} aria-label="search">
        <SearchIcon />
      </IconButton>
      <InputBase
        className={classes.input}
        placeholder={placeholder}
        inputProps={{ 'aria-label': 'search' }}
        onChange={onChange}
      />
    </Paper>
  );
};

InputSearch.defaultProps = {
  placeholder: '',
  onSearch: () => {},
};

InputSearch.propTypes = {
  placeholder: PropTypes.string,
  onSearch: PropTypes.func,
};

export default InputSearch;
