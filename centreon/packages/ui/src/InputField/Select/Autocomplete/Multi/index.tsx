import * as React from 'react';

import { includes, map, prop, reject } from 'ramda';

import { Chip, makeStyles } from '@material-ui/core';
import { UseAutocompleteProps } from '@material-ui/lab';

import Autocomplete, { Props as AutocompleteProps } from '..';
import { SelectEntry } from '../..';
import Option from '../../Option';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    marginRight: theme.spacing(1),
    padding: 0,
  },
  deleteIcon: {
    height: theme.spacing(1.5),
    width: theme.spacing(1.5),
  },
  tag: {
    fontSize: theme.typography.caption.fontSize,
    height: theme.spacing(1.75),
  },
}));

type Multiple = boolean;
type DisableClearable = boolean;
type FreeSolo = boolean;

export interface Props
  extends Omit<AutocompleteProps, 'renderTags' | 'renderOption' | 'multiple'>,
    Omit<
      UseAutocompleteProps<SelectEntry, Multiple, DisableClearable, FreeSolo>,
      'multiple'
    > {
  disableSortedOptions?: boolean;
}

const MultiAutocompleteField = ({
  value,
  options,
  disableSortedOptions = false,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  const renderTags = (renderedValue, getTagProps): Array<JSX.Element> =>
    renderedValue.map((option, index) => (
      <Chip
        classes={{
          deleteIcon: classes.deleteIcon,
          root: classes.tag,
        }}
        key={option.id}
        label={option.name}
        size="small"
        {...getTagProps({ index })}
      />
    ));

  const values = (value as Array<SelectEntry>) || [];

  const isOptionSelected = ({ id }): boolean => {
    const valueIds = map(prop('id'), values);

    return includes(id, valueIds);
  };

  const autocompleteOptions = disableSortedOptions
    ? options
    : [...values, ...reject(isOptionSelected, options)];

  return (
    <Autocomplete
      disableCloseOnSelect
      displayOptionThumbnail
      multiple
      getLimitTagsText={(more): JSX.Element => <Option>{`+${more}`}</Option>}
      options={autocompleteOptions}
      renderOption={(option, { selected }): JSX.Element => (
        <Option checkboxSelected={selected}>{option.name}</Option>
      )}
      renderTags={renderTags}
      value={value}
      {...props}
    />
  );
};

export default MultiAutocompleteField;
