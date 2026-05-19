import { Chip, type ChipProps, Tooltip } from '@mui/material';
import type { AutocompleteRenderGetTagProps } from '@mui/material/Autocomplete';
import type { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import { compose, includes, map, prop, reject, sortBy, toLower } from 'ramda';
import type { JSX } from 'react';

import type { SelectEntry } from '../..';
import Option from '../../Option';
import Autocomplete, { type Props as AutocompleteProps } from '..';
import ListboxComponent from './Listbox';
import { useStyles } from './Multi.styles';

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export interface Props
  extends Omit<AutocompleteProps, 'renderTags' | 'renderOption' | 'multiple'>,
    Omit<
      UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
      'multiple'
    > {
  chipProps?: ChipProps;
  disableSortedOptions?: boolean;
  disableSelectAll?: boolean;
  getOptionTooltipLabel?: (option: SelectEntry) => string;
  getTagLabel?: (option: SelectEntry) => string;
  optionProperty?: string;
  customRenderTags?: (tags: React.ReactNode) => React.ReactNode;
  total?: number;
}

const MultiAutocompleteField = ({
  value,
  options,
  disableSortedOptions = false,
  disableSelectAll = true,
  optionProperty = 'name',
  getOptionLabel = (option: SelectEntry | string): string =>
    typeof option === 'string' ? option : option?.name,
  getTagLabel = (option: SelectEntry): string =>
    (option as unknown as Record<string, string>)[optionProperty],
  getOptionTooltipLabel,
  chipProps,
  customRenderTags,
  onChange,
  total,
  ...props
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const renderTags = (
    renderedValue: Array<SelectEntry>,
    getTagProps: AutocompleteRenderGetTagProps
  ): Array<JSX.Element> =>
    renderedValue.map((option: SelectEntry, index: number) => {
      return (
        <Tooltip
          key={option.id}
          placement="top"
          title={getOptionTooltipLabel?.(option)}
        >
          <Chip
            classes={{
              deleteIcon: classes.deleteIcon,
              root: classes.tag
            }}
            data-testid={`tag-option-chip-${option.id}`}
            label={getTagLabel(option)}
            size="medium"
            {...getTagProps({ index })}
            {...chipProps}
            onDelete={(event) =>
              (
                chipProps?.onDelete as
                  | ((event: React.SyntheticEvent, option: SelectEntry) => void)
                  | undefined
              )?.(event, option)
            }
          />
        </Tooltip>
      );
    });

  const getLimitTagsText = (more: number): JSX.Element => (
    <Option>{`+${more}`}</Option>
  );

  const values = (value as Array<SelectEntry>) || [];

  const isOptionSelected = ({ id }: { id: SelectEntry['id'] }): boolean => {
    const valueIds = map(prop('id'), values);

    return includes(id, valueIds);
  };

  const sortByName = sortBy(compose(toLower, prop(optionProperty)));

  const autocompleteOptions = disableSortedOptions
    ? options
    : sortByName([...values, ...reject(isOptionSelected, options)]);

  return (
    <Autocomplete
      {...({
        disableCloseOnSelect: true,
        displayOptionThumbnail: true,
        getLimitTagsText,
        ListboxComponent: ListboxComponent({
          disableSelectAll,
          isOptionSelected,
          onChange: onChange as (
            event: React.SyntheticEvent,
            value: Array<SelectEntry>,
            reason: string
          ) => void,
          options: options as Array<SelectEntry>,
          total,
          value: values
        }),
        multiple: true,
        onChange,
        options: autocompleteOptions,
        renderOption: (renderProps, option, { selected }): JSX.Element => (
          <li
            key={option.id}
            {...(renderProps as React.HTMLAttributes<HTMLLIElement>)}
          >
            <Option checkboxSelected={selected}>
              {getOptionLabel(option)}
            </Option>
          </li>
        ),
        renderTags: (renderedValue, getTagProps): React.ReactNode =>
          customRenderTags
            ? customRenderTags(renderTags(renderedValue, getTagProps))
            : renderTags(renderedValue, getTagProps),
        value: values,
        ...props
      } as React.ComponentProps<typeof Autocomplete>)}
    />
  );
};

export default MultiAutocompleteField;
