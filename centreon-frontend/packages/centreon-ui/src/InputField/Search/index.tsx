import React from 'react';

import IconSearch from '@material-ui/icons/Search';

import TextField, { Props as TextFieldProps } from '../Text';

type Props = Omit<TextFieldProps, 'StartAdornment'>;

const SearchField = (props: Props): JSX.Element => (
  <TextField StartAdornment={(): JSX.Element => <IconSearch />} {...props} />
);

export default SearchField;
