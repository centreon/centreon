import React from 'react';

import isEqual from 'lodash/isEqual';

import {
  Checkbox,
  makeStyles,
  CircularProgress,
  Chip,
} from '@material-ui/core';
import Autocomplete, { AutocompleteProps } from '@material-ui/lab/Autocomplete';

import { UseAutocompleteMultipleProps } from '@material-ui/lab/useAutocomplete';
import TextField from '../../Text';
import { SelectEntry } from '..';

const useStyles = makeStyles((theme) => ({
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
  tag: {
    fontSize: theme.typography.pxToRem(10),
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
  Omit<UseAutocompleteMultipleProps<SelectEntry>, 'multiple'>;

const AutocompleteField = ({
  options,
  label,
  placeholder = '',
  loading = false,
  onTextChange = (): void => undefined,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  const renderTags = (value, getTagProps): Array<JSX.Element> =>
    value.map((option, index) => (
      <Chip
        classes={{
          root: classes.tag,
        }}
        key={option.id}
        label={option.name}
        size="small"
        {...getTagProps({ index })}
      />
    ));

  return (
    <Autocomplete
      multiple
      size="small"
      options={options}
      disableCloseOnSelect
      loading={loading}
      classes={{ inputRoot: classes.input }}
      getOptionLabel={(option): string => option.name}
      loadingText={<LoadingIndicator />}
      getOptionSelected={isEqual}
      renderOption={(option, { selected }): JSX.Element => (
        <>
          <Checkbox color="primary" checked={selected} />
          {option.name}
        </>
      )}
      renderInput={(params): JSX.Element => (
        <TextField
          {...params}
          label={label}
          placeholder={placeholder}
          onChange={onTextChange}
        />
      )}
      renderTags={renderTags}
      {...props}
    />
  );
};

export default AutocompleteField;
