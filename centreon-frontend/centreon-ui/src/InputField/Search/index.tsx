import React from 'react';

import { Search } from '@material-ui/icons';

import TextField from '../Text';

const SearchField = (props): JSX.Element => (
  <TextField StartAdornment={(): JSX.Element => <Search />} {...props} />
);

export default SearchField;
