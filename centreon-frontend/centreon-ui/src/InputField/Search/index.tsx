import React from 'react';

import IconSearch from '@material-ui/icons/Search';

import TextField from '../Text';

const SearchField = (props): JSX.Element => (
  <TextField StartAdornment={(): JSX.Element => <IconSearch />} {...props} />
);

export default SearchField;
