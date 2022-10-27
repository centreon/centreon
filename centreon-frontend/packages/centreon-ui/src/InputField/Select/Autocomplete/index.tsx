import * as React from 'react';

import { equals, isNil, pick } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  CircularProgress,
  InputAdornment,
  Autocomplete,
  AutocompleteProps,
  useTheme,
} from '@mui/material';
import { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import Option from '../Option';
import TextField from '../../Text';
import { SelectEntry } from '..';
import { searchLabel } from '../../translatedLabels';

export type Props = {
  autoFocus?: boolean;
  dataTestId?: string;
  displayOptionThumbnail?: boolean;
  displayPopupIcon?: boolean;
  endAdornment?: React.ReactElement;
  error?: string;
  hideInput?: boolean;
  label?: string;
  loading?: boolean;
  onTextChange?;
  placeholder?: string | undefined;
  required?: boolean;
} & Omit<
  AutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
  'renderInput'
> &
  UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>;

type StyledProps = Partial<Pick<Props, 'hideInput'>>;

const textfieldHeight = (hideInput?: boolean): number | undefined =>
  hideInput ? 0 : undefined;

const useStyles = makeStyles<StyledProps>()((theme, { hideInput }) => ({
  input: {
    '&:after': {
      borderBottom: 0,
    },
    '&:before': {
      borderBottom: 0,
      content: 'unset',
    },
    '&:hover:before': {
      borderBottom: 0,
    },
    height: textfieldHeight(hideInput),
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
      paddingBottom: hideInput ? 0 : theme.spacing(0.75),
      paddingRight: hideInput ? 0 : theme.spacing(1),
      paddingTop: hideInput ? 0 : theme.spacing(0.75),
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
  textfield: {
    height: textfieldHeight(hideInput),
    visibility: hideInput ? 'hidden' : 'visible',
  },
}));

const LoadingIndicator = (): JSX.Element => {
  const { classes } = useStyles({});

  return (
    <div className={classes.loadingIndicator}>
      <CircularProgress size={20} />
    </div>
  );
};

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

const AutocompleteField = ({
  dataTestId,
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
  hideInput = false,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ hideInput });
  const { t } = useTranslation();
  const theme = useTheme();
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
        'data-testid': dataTestId,
        endAdornment: (
          <>
            {endAdornment && (
              <InputAdornment position="end">{endAdornment}</InputAdornment>
            )}
            {params.InputProps.endAdornment}
          </>
        ),
        style: {
          paddingRight: theme.spacing(5),
        },
      }}
      autoFocus={autoFocus}
      classes={{
        root: classes.textfield,
      }}
      error={error}
      inputProps={{
        ...params.inputProps,
        'aria-label': label,
      }}
      label={label}
      placeholder={isNil(placeholder) ? t(searchLabel) : placeholder}
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
        inputRoot: cx([
          classes.input,
          label ? classes.inputWithLabel : classes.inputWithoutLabel,
        ]),
        popper: classes.popper,
        root: classes.textfield,
      }}
      forcePopupIcon={displayPopupIcon}
      getOptionLabel={(option): string =>
        (option as SelectEntry)?.name?.toString() || ''
      }
      isOptionEqualToValue={areSelectEntriesEqual}
      loading={loading}
      loadingText={<LoadingIndicator />}
      options={options}
      renderInput={renderInput}
      renderOption={(renderProps, option): JSX.Element => {
        return (
          <li className={classes.options} {...renderProps}>
            <Option
              thumbnailUrl={displayOptionThumbnail ? option.url : undefined}
            >
              {option.name}
            </Option>
          </li>
        );
      }}
      size="small"
      {...props}
    />
  );
};

export default AutocompleteField;
