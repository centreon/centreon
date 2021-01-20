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

import Option from '../Option';
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
  inputEndAdornment: {
    paddingBottom: '19px',
  },
  options: {
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1),
    alignItems: 'center',
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
  displayOptionThumbnail?: boolean;
  required?: boolean;
  error?: string;
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
  displayOptionThumbnail = false,
  required = false,
  error,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Autocomplete
      size="small"
      options={options}
      loading={loading}
      classes={{ inputRoot: classes.input }}
      getOptionLabel={(option: SelectEntry): string => option.name}
      loadingText={<LoadingIndicator />}
      getOptionSelected={equals}
      renderOption={(option) => {
        return (
          <div className={classes.options}>
            {displayOptionThumbnail && (
              <img alt={option.name} src={option.url} height={20} width={20} />
            )}

            <Option>{option.name}</Option>
          </div>
        );
      }}
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
          required={required}
          error={error}
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
