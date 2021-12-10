import React from 'react';

import IconSearch from '@material-ui/icons/Search';

import TextField, { Props as TextFieldProps } from '../Text';

type Props = Omit<TextFieldProps, 'StartAdornment'>;

const SearchAdornment = (): JSX.Element => <IconSearch />;

const SearchField = (props: Props): JSX.Element => (
  <TextField StartAdornment={SearchAdornment} {...props} />
);

export default SearchField;
