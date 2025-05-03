import { equals, isEmpty, isNil, pick } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Autocomplete,
  AutocompleteProps,
  CircularProgress,
  InputAdornment,
  InputProps,
  useTheme
} from '@mui/material';
import { AutocompleteSlotsAndSlotProps } from '@mui/material/Autocomplete';
import { TextFieldSlotsAndSlotProps } from '@mui/material/TextField';
import { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import { ForwardedRef, HTMLAttributes, ReactElement, forwardRef } from 'react';
import { SelectEntry } from '..';
import { getNormalizedId } from '../../../utils';
import TextField from '../../Text';
import { labelClear, labelOpen, searchLabel } from '../../translatedLabels';
import Option from '../Option';
import { useAutoCompleteStyles } from './autoComplete.styles';

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
  forceInputRenderValue?: boolean;
  textFieldSlotsAndSlotProps?: TextFieldSlotsAndSlotProps<InputProps>;
  autocompleteSlotsAndSlotProps?: AutocompleteSlotsAndSlotProps<
    SelectEntry,
    Multiple,
    DisableClearable,
    FreeSolo
  >;
} & Omit<
  AutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
  'renderInput'
> &
  UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>;

const LoadingIndicator = (): JSX.Element => {
  const { classes } = useAutoCompleteStyles({});

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
      forceInputRenderValue = false,
      textFieldSlotsAndSlotProps,
      autocompleteSlotsAndSlotProps,
      ...autocompleteProps
    }: Props,
    ref?: ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes, cx } = useAutoCompleteStyles({ hideInput });
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
          autoFocus={autoFocus}
          autoSize={autoSize}
          autoSizeCustomPadding={7 + (autoSizeCustomPadding || 0)}
          autoSizeDefaultWidth={autoSizeDefaultWidth}
          classes={{
            root: classes.textfield
          }}
          error={error}
          externalValueForAutoSize={autocompleteProps?.value?.name}
          label={label}
          placeholder={isNil(placeholder) ? t(searchLabel) : placeholder}
          required={required}
          value={
            inputValue ||
            (forceInputRenderValue
              ? getOptionItemLabel(autocompleteProps?.value || undefined)
              : undefined) ||
            undefined
          }
          onChange={onTextChange}
          slotProps={{
            input: {
              ...params.InputProps,
              endAdornment: (
                <>
                  {endAdornment && (
                    <InputAdornment position="end">
                      {endAdornment}
                    </InputAdornment>
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
            },
            inputLabel: {
              classes: {
                marginDense: classes.inputLabel,
                shrink: classes.inputLabelShrink
              }
            },
            htmlInput: {
              ...params.inputProps,
              'aria-label': label,
              'data-testid': dataTestId || label,
              id: getNormalizedId({ idToNormalize: label || '' }),
              ...(forceInputRenderValue
                ? {
                    value: getOptionItemLabel(
                      autocompleteProps?.value || undefined
                    )
                  }
                : {}),
              ...textFieldSlotsAndSlotProps?.slotProps?.htmlInput
            }
          }}
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
        slotProps={{
          ...autocompleteSlotsAndSlotProps?.slotProps,
          clearIndicator: {
            title: t(labelClear)
          },
          popupIndicator: {
            title: t(labelOpen)
          }
        }}
        {...autocompleteProps}
      />
    );
  }
);

export default AutocompleteField;
