import * as React from 'react';

import { equals, pick } from 'ramda';
import clsx from 'clsx';
import { useTranslation } from 'react-i18next';

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
import { searchLabel } from '../../translatedLabels';

const useStyles = makeStyles((theme) => ({
  input: {
    '&:after': {
      borderBottom: 0,
    },
    '&:before': {
      borderBottom: 0,
    },
    '&:hover:before': {
      borderBottom: 0,
    },
  },
  inputEndAdornment: {
    paddingBottom: '19px',
  },
  inputLabel: {
    '&&': {
      fontSize: theme.typography.body1.fontSize,
      maxWidth: '72%',
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      transform: 'translate(12px, 14px) scale(1)',
      whiteSpace: 'nowrap',
    },
  },
  inputLabelShrink: {
    '&&': {
      maxWidth: '90%',
    },
  },
  inputWithLabel: {
    '&[class*="MuiFilledInput-root"]': {
      paddingTop: theme.spacing(2),
    },
    paddingTop: theme.spacing(1),
  },
  inputWithoutLabel: {
    '&[class*="MuiFilledInput-root"][class*="MuiFilledInput-marginDense"]': {
      paddingBottom: theme.spacing(0.75),
      paddingRight: theme.spacing(1),
      paddingTop: theme.spacing(0.75),
    },
  },
  loadingIndicator: {
    textAlign: 'center',
  },
  options: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1),
  },
  popper: {
    zIndex: theme.zIndex.tooltip + 1,
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
  autoFocus?: boolean;
  displayOptionThumbnail?: boolean;
  displayPopupIcon?: boolean;
  endAdornment?: React.ReactElement;
  error?: string;
  label?: string;
  loading?: boolean;
  onTextChange?;
  placeholder?: string;
  required?: boolean;
} & Omit<
  AutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
  'renderInput'
> &
  UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>;

const AutocompleteField = ({
  options,
  label,
  placeholder,
  loading = false,
  onTextChange = (): void => undefined,
  endAdornment = undefined,
  inputValue,
  displayOptionThumbnail = false,
  required = false,
  error,
  displayPopupIcon = true,
  autoFocus = false,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  const areSelectEntriesEqual = (option, value): boolean => {
    const identifyingProps = ['id', 'name'];

    return equals(
      pick(identifyingProps, option),
      pick(identifyingProps, value),
    );
  };

  const renderInput = (params): JSX.Element => (
    <TextField
      {...params}
      InputLabelProps={{
        classes: {
          marginDense: classes.inputLabel,
          shrink: classes.inputLabelShrink,
        },
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
      autoFocus={autoFocus}
      error={error}
      inputProps={{
        ...params.inputProps,
        'aria-label': label,
      }}
      label={label}
      placeholder={placeholder || t(searchLabel)}
      required={required}
      value={inputValue || ''}
      onChange={onTextChange}
    />
  );

  return (
    <Autocomplete
      disableClearable
      classes={{
        groupLabel: classes.inputLabel,
        inputRoot: clsx([
          classes.input,
          label ? classes.inputWithLabel : classes.inputWithoutLabel,
        ]),
        popper: classes.popper,
      }}
      forcePopupIcon={displayPopupIcon}
      getOptionLabel={(option: SelectEntry): string => option.name}
      getOptionSelected={areSelectEntriesEqual}
      loading={loading}
      loadingText={<LoadingIndicator />}
      options={options}
      renderInput={renderInput}
      renderOption={(option): JSX.Element => {
        return (
          <div className={classes.options}>
            {displayOptionThumbnail && (
              <img alt={option.name} height={20} src={option.url} width={20} />
            )}

            <Option>{option.name}</Option>
          </div>
        );
      }}
      size="small"
      {...props}
    />
  );
};

export default AutocompleteField;
