import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';
import { makeStyles } from '@material-ui/core/styles';
import Input from '@material-ui/core/Input';
import InputAdornment from '@material-ui/core/InputAdornment';
import SearchIcon from '@material-ui/icons/Search';

const useAdornmentStyles = makeStyles((theme) => ({
  searchIcon: {
    color: theme.palette.grey[400],
  },
}));

const StartAdornment = () => {
  const classes = useAdornmentStyles();

  return (
    <InputAdornment position="start">
      <SearchIcon
        classes={{
          root: classes.searchIcon,
        }}
      />
    </InputAdornment>
  );
};

const useStyles = makeStyles((theme) => {
  // took it from material-ui source code
  const light = theme.palette.type === 'light';
  const bottomLineColor = light
    ? 'rgba(0, 0, 0, 0.42)'
    : 'rgba(255, 255, 255, 0.7)';

  return {
    root: {
      backgroundColor: theme.palette.common.white,
      border: `1px solid ${theme.palette.grey[400]}`,
      height: 32,
      paddingLeft: 6,
      fontSize: 13,
    },
    input: {
      paddingBottom: 4,
    },
    underline: {
      '&:hover:not($disabled):before': {
        borderBottom: `2px solid ${bottomLineColor}`,
      },
    },
    disabled: {},
  };
});

const SearchInput = forwardRef((props, ref) => {
  const classes = useStyles();

  return (
    <Input
      classes={{
        root: classes.root,
        input: classes.input,
        underline: classes.underline,
      }}
      margin="dense"
      ref={ref}
      startAdornment={<StartAdornment />}
      {...props}
    />
  );
});

SearchInput.propTypes = {
  placeholder: PropTypes.string,
  value: PropTypes.string,
  onChange: PropTypes.func.isRequired,
};

SearchInput.defaultProps = {
  placeholder: 'Search',
  value: null,
};

export default SearchInput;
