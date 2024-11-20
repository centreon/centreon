import { equals, isEmpty, isNil, pick } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  Autocomplete,
  AutocompleteProps,
  CircularProgress,
  InputAdornment,
  useTheme
} from '@mui/material';
import { autocompleteClasses } from '@mui/material/Autocomplete';
import { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import { ThemeMode } from '@centreon/ui-context';

import { ForwardedRef, HTMLAttributes, ReactElement, forwardRef } from 'react';
import { SelectEntry } from '..';
import { getNormalizedId } from '../../../utils';
import TextField from '../../Text';
import { searchLabel } from '../../translatedLabels';
import Option from '../Option';

export type Props = {
  autoFocus?: boolean;
  autoSize?: boolean;
  autoSizeCustomPadding?: number;
  autoSizeDefaultWidth?: number;
  dataTestId?: string;
  displayOptionThumbnail?: boolean;
  displayPopupIcon?: boolean;
  endAdornment?: ReactElement;
  error?: string;
  getOptionItemLabel?: (option) => string;
  hideInput?: boolean;
  label: string;
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
  hiddenText: {
    transform: 'scale(0)'
  },
  input: {
    '&:after': {
      borderBottom: 0
    },
    '&:before': {
      borderBottom: 0,
      content: 'unset'
    },
    '&:hover:before': {
      borderBottom: 0
    },
    height: textfieldHeight(hideInput)
  },
  inputLabel: {
    '&&': {
      fontSize: theme.typography.body1.fontSize,
      maxWidth: '72%',
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      transform: 'translate(12px, 14px) scale(1)',
      whiteSpace: 'nowrap'
    }
  },
  inputLabelShrink: {
    '&&': {
      maxWidth: '90%'
    }
  },
  inputWithLabel: {
    '&[class*="MuiFilledInput-root"]': {
      paddingTop: theme.spacing(2)
    },
    paddingTop: theme.spacing(1)
  },
  inputWithoutLabel: {
    '&[class*="MuiFilledInput-root"][class*="MuiFilledInput-marginDense"]': {
      paddingBottom: hideInput ? 0 : theme.spacing(0.75),
      paddingRight: hideInput ? 0 : theme.spacing(1),
      paddingTop: hideInput ? 0 : theme.spacing(0.75)
    }
  },
  loadingIndicator: {
    textAlign: 'center'
  },
  options: {
    alignItems: 'center',
    display: 'grid',
    gridAutoFlow: 'column',
    gridGap: theme.spacing(1)
  },
  popper: {
    [`& .${autocompleteClasses.listbox}`]: {
      [`& .${autocompleteClasses.option}`]: {
        [`&:hover, &[aria-selected="true"], &.${autocompleteClasses.focused},
        &.${autocompleteClasses.focused}[aria-selected="true"]`]: {
          background: equals(theme.palette.mode, ThemeMode.dark)
            ? theme.palette.primary.dark
            : theme.palette.primary.light,
          color: equals(theme.palette.mode, ThemeMode.dark)
            ? theme.palette.common.white
            : theme.palette.primary.main
        }
      },
      padding: 0
    },
    zIndex: theme.zIndex.tooltip + 1
  },
  textfield: {
    height: textfieldHeight(hideInput),
    visibility: hideInput ? 'hidden' : 'visible'
  }
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

const AutocompleteField = forwardRef(
  (
    {
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
      dataTestId,
      autoSize = false,
      autoSizeDefaultWidth = 0,
      autoSizeCustomPadding,
      getOptionItemLabel = (option) => option?.name,
      ...autocompleteProps
    }: Props,
    ref?: ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes, cx } = useStyles({ hideInput });
    const { t } = useTranslation();
    const theme = useTheme();

    const areSelectEntriesEqual = (option, value): boolean => {
      const identifyingProps = ['id', 'name'];

      return equals(
        pick(identifyingProps, option),
        pick(identifyingProps, value)
      );
    };

    const renderInput = (params): JSX.Element => {
      return (
        <TextField
          {...params}
          InputLabelProps={{
            classes: {
              marginDense: classes.inputLabel,
              shrink: classes.inputLabelShrink
            }
          }}
          InputProps={{
            ...params.InputProps,
            endAdornment: (
              <>
                {endAdornment && (
                  <InputAdornment position="end">{endAdornment}</InputAdornment>
                )}
                {params.InputProps.endAdornment}
              </>
            ),
            style: {
              background: 'transparent',
              minWidth: 0,
              padding: theme.spacing(
                0.75,
                isEmpty(placeholder) ? 0 : 5,
                0.75,
                0.75
              )
            }
          }}
          autoFocus={autoFocus}
          autoSize={autoSize}
          autoSizeCustomPadding={7 + (autoSizeCustomPadding || 0)}
          autoSizeDefaultWidth={autoSizeDefaultWidth}
          classes={{
            root: classes.textfield
          }}
          error={error}
          externalValueForAutoSize={autocompleteProps?.value?.name}
          inputProps={{
            ...params.inputProps,
            'aria-label': label,
            'data-testid': dataTestId || label,
            id: getNormalizedId(label || ''),
            value: getOptionItemLabel(autocompleteProps?.value || undefined),
            ...autocompleteProps?.inputProps
          }}
          label={label}
          placeholder={isNil(placeholder) ? t(searchLabel) : placeholder}
          required={required}
          value={
            inputValue ||
            getOptionItemLabel(autocompleteProps?.value || undefined) ||
            undefined
          }
          onChange={onTextChange}
        />
      );
    };

    return (
      <Autocomplete
        disableClearable
        classes={{
          groupLabel: classes.inputLabel,
          inputRoot: cx([
            classes.input,
            label ? classes.inputWithLabel : classes.inputWithoutLabel
          ]),
          popper: classes.popper,
          root: classes.textfield
        }}
        forcePopupIcon={displayPopupIcon}
        getOptionLabel={(option): string =>
          (option as SelectEntry)?.name?.toString() || ''
        }
        isOptionEqualToValue={areSelectEntriesEqual}
        loading={loading}
        loadingText={<LoadingIndicator />}
        options={options}
        ref={ref}
        renderInput={renderInput}
        renderOption={(props, option): JSX.Element => {
          return (
            <li
              className={classes.options}
              {...(props as HTMLAttributes<HTMLLIElement>)}
            >
              <Option
                thumbnailUrl={displayOptionThumbnail ? option.url : undefined}
              >
                {getOptionItemLabel(option)}
              </Option>
            </li>
          );
        }}
        size="small"
        {...autocompleteProps}
      />
    );
  }
);

export default AutocompleteField;
