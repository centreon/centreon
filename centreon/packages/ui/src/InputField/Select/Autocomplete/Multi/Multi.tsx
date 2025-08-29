import { compose, includes, map, prop, reject, sortBy, toLower } from 'ramda';
import { JSX } from 'react';

import { Chip, ChipProps, Tooltip } from '@mui/material';
import { UseAutocompleteProps } from '@mui/material/useAutocomplete';

import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import Option from '../../Option';
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
  getOptionTooltipLabel?: (option) => string;
  getTagLabel?: (option) => string;
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
  getOptionLabel = (option): string => option.name,
  getTagLabel = (option): string => option[optionProperty],
  getOptionTooltipLabel,
  chipProps,
  customRenderTags,
  onChange,
  total,
  ...props
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const renderTags = (renderedValue, getTagProps): Array<JSX.Element> =>
    renderedValue.map((option, index) => {
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
            onDelete={(event) => chipProps?.onDelete?.(event, option)}
          />
        </Tooltip>
      );
    });

  const getLimitTagsText = (more): JSX.Element => <Option>{`+${more}`}</Option>;

  const values = (value as Array<SelectEntry>) || [];

  const isOptionSelected = ({ id }): boolean => {
    const valueIds = map(prop('id'), values);

    return includes(id, valueIds);
  };

  const sortByName = sortBy(compose(toLower, prop(optionProperty)));

  const autocompleteOptions = disableSortedOptions
    ? options
    : sortByName([...values, ...reject(isOptionSelected, options)]);

  return (
    <Autocomplete
      disableCloseOnSelect
      displayOptionThumbnail
      multiple
      getLimitTagsText={getLimitTagsText}
      options={autocompleteOptions}
      renderOption={(renderProps, option, { selected }): JSX.Element => (
        <li
          key={option.id}
          {...(renderProps as React.HTMLAttributes<HTMLLIElement>)}
        >
          <Option checkboxSelected={selected}>{getOptionLabel(option)}</Option>
        </li>
      )}
      value={values}
      renderTags={(renderedValue, getTagProps): React.ReactNode =>
        customRenderTags
          ? customRenderTags(renderTags(renderedValue, getTagProps))
          : renderTags(renderedValue, getTagProps)
      }
      ListboxComponent={ListboxComponent({
        total,
        onChange,
        isOptionSelected,
        disableSelectAll,
        options
      })}
      onChange={onChange}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
