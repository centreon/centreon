import * as React from 'react';

import { equals } from 'ramda';

import { makeStyles, CircularProgress } from '@material-ui/core';
import Autocomplete, { AutocompleteProps } from '@material-ui/lab/Autocomplete';
import { UseAutocompleteProps } from '@material-ui/lab/useAutocomplete';

import TextField from '../../Text';
import { SelectEntry } from '..';

const useStyles = makeStyles(() => ({
  loadingIndicator: {
    textAlign: 'center',
  },
  input: {
    '&:before': {
      borderBottom: 0,
    },
    '&:after': {
      borderBottom: 0,
    },
    '&:hover:before': {
      borderBottom: 0,
    },
  },
}));

const LoadingIndicator = (): JSX.Element => {
  const classes = useStyles();

  return (
    <div className={classes.loadingIndicator}>
      <CircularProgress size={20} />
    </div>
  );
};

export type Props = {
  loading?: boolean;
  onTextChange?;
  label: string;
  placeholder?: string;
} & Omit<AutocompleteProps<SelectEntry>, 'renderInput'> &
  UseAutocompleteProps<SelectEntry>;

const AutocompleteField = ({
  options,
  label,
  placeholder = '',
  loading = false,
  onTextChange = (): void => undefined,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Autocomplete
      size="small"
      options={options}
      disableCloseOnSelect
      loading={loading}
      classes={{ inputRoot: classes.input }}
      getOptionLabel={(option): string => option.name}
      loadingText={<LoadingIndicator />}
      getOptionSelected={equals}
      renderInput={(params): JSX.Element => (
        <TextField
          {...params}
          label={label}
          placeholder={placeholder}
          onChange={onTextChange}
        />
      )}
      {...props}
    />
  );
};

export default AutocompleteField;
