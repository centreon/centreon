import * as React from 'react';

import { equals } from 'ramda';

import {
  makeStyles,
  CircularProgress,
  InputAdornment,
} from '@material-ui/core';
import {
  Autocomplete,
  AutocompleteProps,
  UseAutocompleteProps,
} from '@material-ui/lab';

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
  inputEndAdornment: {
    paddingBottom: '19px',
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

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export type Props = {
  loading?: boolean;
  onTextChange?;
  label: string;
  placeholder?: string;
  endAdornment?: React.ReactElement;
} & Omit<
  AutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
  'renderInput'
> &
  UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>;

const AutocompleteField = ({
  options,
  label,
  placeholder = '',
  loading = false,
  onTextChange = (): void => undefined,
  endAdornment = undefined,
  inputValue,
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
      getOptionLabel={(option: SelectEntry): string => option.name}
      loadingText={<LoadingIndicator />}
      getOptionSelected={equals}
      renderInput={(params): JSX.Element => (
        <TextField
          {...params}
          label={label}
          placeholder={placeholder}
          onChange={onTextChange}
          value={inputValue || ''}
          inputProps={{
            ...params.inputProps,
            'aria-label': label,
          }}
          InputProps={{
            ...params.InputProps,
            endAdornment: (
              <>
                {endAdornment && (
                  <InputAdornment
                    classes={{ root: classes.inputEndAdornment }}
                    position="end"
                  >
                    {endAdornment}
                  </InputAdornment>
                )}
                {params.InputProps.endAdornment}
              </>
            ),
          }}
        />
      )}
      {...props}
    />
  );
};

export default AutocompleteField;
